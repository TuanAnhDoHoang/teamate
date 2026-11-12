<?php

namespace App\Services;

use App\Exceptions\EncryptedFileException;
use App\Exceptions\VaultTransitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class VaultFileEncryption
{
    private const FORMAT = 'TEAMATE-ENCRYPTED-FILE-1';

    private const ALGORITHM = 'aes-256-gcm-chunked-v1';

    private const CHUNK_BYTES = 65536;

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    /**
     * Encrypt a local plaintext file into the Teamate streaming envelope.
     *
     * @return array{key_ref: string, key_version: string}
     */
    public function encryptFile(string $plaintextPath, string $encryptedPath): array
    {
        $keyName = $this->keyName();
        [$key, $wrappedKey, $keyVersion] = $this->generateDataKey($keyName);
        $source = fopen($plaintextPath, 'rb');
        $destination = fopen($encryptedPath, 'wb');

        if (! is_resource($source) || ! is_resource($destination)) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($destination)) {
                fclose($destination);
            }

            sodium_memzero($key);

            throw new EncryptedFileException('Unable to open a file for encryption.');
        }

        if (! chmod($encryptedPath, 0600)) {
            fclose($source);
            fclose($destination);
            sodium_memzero($key);

            throw new EncryptedFileException('Unable to secure the temporary encrypted file.');
        }

        try {
            $header = json_encode([
                'format' => self::FORMAT,
                'algorithm' => self::ALGORITHM,
                'wrapped_key' => $wrappedKey,
            ], JSON_THROW_ON_ERROR);

            $this->writeAll($destination, self::FORMAT."\n".$header."\n");
            $frameNumber = 0;

            while (! feof($source)) {
                $plaintext = fread($source, self::CHUNK_BYTES);

                if ($plaintext === false) {
                    throw new EncryptedFileException('Unable to read a file for encryption.');
                }

                if ($plaintext === '') {
                    continue;
                }

                $nonce = random_bytes(self::NONCE_BYTES);
                $tag = '';
                $ciphertext = openssl_encrypt(
                    $plaintext,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    $this->associatedData($frameNumber, 0),
                    self::TAG_BYTES,
                );

                if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
                    throw new EncryptedFileException('Unable to encrypt file data.');
                }

                $this->writeFrame($destination, 0, $nonce, $tag, $ciphertext);
                $frameNumber++;
            }

            $nonce = random_bytes(self::NONCE_BYTES);
            $tag = '';
            $finalTag = openssl_encrypt(
                '',
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                $this->associatedData($frameNumber, 1),
                self::TAG_BYTES,
            );

            if ($finalTag === false || $finalTag !== '' || strlen($tag) !== self::TAG_BYTES) {
                throw new EncryptedFileException('Unable to finalize encrypted file data.');
            }

            $this->writeFrame(
                $destination,
                1,
                $nonce,
                $tag,
                '',
            );
        } catch (JsonException $exception) {
            throw new EncryptedFileException('Unable to encode encrypted file metadata.', previous: $exception);
        } finally {
            fclose($source);
            fclose($destination);
            sodium_memzero($key);
        }

        return [
            'key_ref' => $keyName,
            'key_version' => $keyVersion,
        ];
    }

    /**
     * Decrypt a Teamate envelope from one stream into another.
     *
     * @param  resource  $encryptedStream
     * @param  resource  $plaintextStream
     */
    public function decryptStream(mixed $encryptedStream, mixed $plaintextStream, string $keyName): void
    {
        if (! is_resource($encryptedStream) || ! is_resource($plaintextStream)) {
            throw new EncryptedFileException('Invalid file stream supplied for decryption.');
        }

        if (! hash_equals($this->keyName(), $keyName)) {
            throw new EncryptedFileException('The file references an unsupported encryption key.');
        }

        $format = fgets($encryptedStream, 128);
        $headerLine = fgets($encryptedStream, 8193);

        if ($format !== self::FORMAT."\n" || $headerLine === false || ! str_ends_with($headerLine, "\n")) {
            throw new EncryptedFileException('The encrypted file header is invalid.');
        }

        try {
            $header = json_decode($headerLine, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EncryptedFileException('The encrypted file metadata is invalid.', previous: $exception);
        }

        if (! is_array($header)
            || ($header['format'] ?? null) !== self::FORMAT
            || ($header['algorithm'] ?? null) !== self::ALGORITHM
            || ! is_string($header['wrapped_key'] ?? null)
        ) {
            throw new EncryptedFileException('The encrypted file metadata is invalid.');
        }

        $key = $this->decryptDataKey($keyName, $header['wrapped_key']);

        if (strlen($key) !== 32) {
            sodium_memzero($key);

            throw new EncryptedFileException('Vault returned an invalid file encryption key.');
        }

        try {
            $finalFrameSeen = false;
            $frameNumber = 0;

            while (! feof($encryptedStream)) {
                $frameHeader = $this->readExact($encryptedStream, 1 + 4 + self::NONCE_BYTES + self::TAG_BYTES);

                if ($frameHeader === null) {
                    break;
                }

                $frameType = ord($frameHeader[0]);
                $unpackedLength = unpack('Nlength', substr($frameHeader, 1, 4));

                if ($unpackedLength === false) {
                    throw new EncryptedFileException('The encrypted file contains an invalid frame.');
                }

                $frameLength = $unpackedLength['length'];
                $nonce = substr($frameHeader, 5, self::NONCE_BYTES);
                $tag = substr($frameHeader, 5 + self::NONCE_BYTES, self::TAG_BYTES);

                if (($frameType === 0 && ($frameLength === 0 || $frameLength > self::CHUNK_BYTES))
                    || ($frameType === 1 && $frameLength !== 0)
                    || ! in_array($frameType, [0, 1], true)) {
                    throw new EncryptedFileException('The encrypted file contains an invalid frame.');
                }

                $ciphertext = $frameLength === 0
                    ? ''
                    : $this->readExact($encryptedStream, $frameLength);

                if ($ciphertext === null) {
                    throw new EncryptedFileException('The encrypted file is truncated.');
                }

                $plaintext = openssl_decrypt(
                    $ciphertext,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    $this->associatedData($frameNumber, $frameType),
                );

                if ($plaintext === false) {
                    throw new EncryptedFileException('The encrypted file failed its integrity check.');
                }

                if ($frameType === 1) {
                    $finalFrameSeen = true;

                    if (fread($encryptedStream, 1) !== '') {
                        throw new EncryptedFileException('The encrypted file contains trailing data.');
                    }
                    break;
                }

                $this->writeAll($plaintextStream, $plaintext);
                $frameNumber++;
            }

            if (! $finalFrameSeen) {
                throw new EncryptedFileException('The encrypted file is incomplete.');
            }
        } finally {
            sodium_memzero($key);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function generateDataKey(string $keyName): array
    {
        $data = $this->vaultRequest('transit/datakey/plaintext/'.$keyName, ['bits' => 256]);
        $plaintext = base64_decode($data['plaintext'] ?? '', true);
        $ciphertext = $data['ciphertext'] ?? null;

        if ($plaintext === false
            || strlen($plaintext) !== 32
            || ! is_string($ciphertext)
            || preg_match('/^vault:v([1-9][0-9]*):/', $ciphertext, $matches) !== 1) {
            if (is_string($plaintext) && $plaintext !== '') {
                sodium_memzero($plaintext);
            }

            throw new VaultTransitException('Vault returned an invalid data key response.');
        }

        return [$plaintext, $ciphertext, (string) $matches[1]];
    }

    private function decryptDataKey(string $keyName, string $wrappedKey): string
    {
        if (preg_match('/^vault:v[1-9][0-9]*:/', $wrappedKey) !== 1) {
            throw new EncryptedFileException('The encrypted file contains an invalid wrapped key.');
        }

        $data = $this->vaultRequest('transit/decrypt/'.$keyName, ['ciphertext' => $wrappedKey]);
        $plaintext = base64_decode($data['plaintext'] ?? '', true);

        if ($plaintext === false) {
            throw new VaultTransitException('Vault returned an invalid decrypted key response.');
        }

        return $plaintext;
    }

    /**
     * @param  array<string, int|string>  $payload
     * @return array<string, mixed>
     */
    private function vaultRequest(string $path, array $payload): array
    {
        $token = (string) config('services.vault.token', '');
        $address = rtrim((string) config('services.vault.address', ''), '/');
        $scheme = parse_url($address, PHP_URL_SCHEME);

        if ($token === '' || ! filter_var($address, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new VaultTransitException('Vault address or authentication is not configured.');
        }

        if (app()->isProduction() && $scheme !== 'https') {
            throw new VaultTransitException('Vault must use HTTPS in production.');
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout((int) config('services.vault.timeout', 5))
                ->post($address.'/v1/'.$path, $payload);
        } catch (ConnectionException $exception) {
            throw new VaultTransitException('Vault could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new VaultTransitException('Vault rejected the file encryption request (HTTP '.$response->status().').');
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new VaultTransitException('Vault returned an invalid response.');
        }

        return $data;
    }

    private function keyName(): string
    {
        $keyName = (string) config('services.vault.transit_key', '');

        if (preg_match('/^[A-Za-z0-9_-]+$/', $keyName) !== 1) {
            throw new VaultTransitException('Vault Transit key name is not configured correctly.');
        }

        return $keyName;
    }

    /**
     * @param  resource  $stream
     */
    private function writeFrame(
        mixed $stream,
        int $frameType,
        string $nonce,
        string $tag,
        string $ciphertext,
    ): void {
        $this->writeAll(
            $stream,
            pack('C', $frameType).pack('N', strlen($ciphertext)).$nonce.$tag.$ciphertext,
        );
    }

    private function associatedData(int $frameNumber, int $frameType): string
    {
        return self::FORMAT."\0".pack('N2', 0, $frameNumber).pack('C', $frameType);
    }

    /**
     * @param  resource  $stream
     */
    private function writeAll(mixed $stream, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));

            if ($written === false || $written === 0) {
                throw new EncryptedFileException('Unable to write encrypted file data.');
            }

            $offset += $written;
        }
    }

    /**
     * @param  resource  $stream
     */
    private function readExact(mixed $stream, int $length): ?string
    {
        if ($length < 1) {
            throw new EncryptedFileException('Invalid encrypted file read length.');
        }

        $contents = '';

        while (strlen($contents) < $length) {
            $remaining = $length - strlen($contents);

            if ($remaining < 1) {
                break;
            }

            $chunk = fread($stream, $remaining);

            if ($chunk === false) {
                throw new EncryptedFileException('Unable to read encrypted file data.');
            }

            if ($chunk === '') {
                return $contents === '' ? null : throw new EncryptedFileException('The encrypted file is truncated.');
            }

            $contents .= $chunk;
        }

        return $contents;
    }
}
