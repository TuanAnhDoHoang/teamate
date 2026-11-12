<?php

use App\Exceptions\EncryptedFileException;
use App\Exceptions\VaultTransitException;
use App\Services\VaultFileEncryption;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function configureVaultFileEncryptionTest(): void
{
    config([
        'services.vault.address' => 'http://vault.test',
        'services.vault.token' => 'test-vault-token',
        'services.vault.transit_key' => 'teamate-files',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://vault.test/v1/transit/datakey/plaintext/teamate-files' => Http::response([
            'data' => [
                'plaintext' => base64_encode(str_repeat('k', 32)),
                'ciphertext' => 'vault:v1:d3JhcHBlZC1kYXRhLWtleQ==',
            ],
        ]),
        'http://vault.test/v1/transit/decrypt/teamate-files' => Http::response([
            'data' => [
                'plaintext' => base64_encode(str_repeat('k', 32)),
            ],
        ]),
    ]);
}

test('file content is encrypted and can be decrypted with its Vault-wrapped key', function () {
    configureVaultFileEncryptionTest();
    $plaintextPath = tempnam(sys_get_temp_dir(), 'teamate-plaintext-');
    $encryptedPath = tempnam(sys_get_temp_dir(), 'teamate-encrypted-');
    $plaintext = str_repeat('private file content', 4000);
    file_put_contents($plaintextPath, $plaintext);

    try {
        $metadata = app(VaultFileEncryption::class)->encryptFile($plaintextPath, $encryptedPath);
        $encryptedContents = file_get_contents($encryptedPath);
        $encryptedStream = fopen($encryptedPath, 'rb');
        $decryptedStream = fopen('php://temp', 'w+b');

        app(VaultFileEncryption::class)->decryptStream($encryptedStream, $decryptedStream, $metadata['key_ref']);

        rewind($decryptedStream);

        expect($metadata)->toBe([
            'key_ref' => 'teamate-files',
            'key_version' => '1',
        ]);
        expect($encryptedContents)
            ->toStartWith("TEAMATE-ENCRYPTED-FILE-1\n")
            ->not->toContain($plaintext);
        expect(stream_get_contents($decryptedStream))->toBe($plaintext);
    } finally {
        if (is_resource($encryptedStream ?? null)) {
            fclose($encryptedStream);
        }

        if (is_resource($decryptedStream ?? null)) {
            fclose($decryptedStream);
        }

        unlink($plaintextPath);
        unlink($encryptedPath);
    }
});

test('empty files are encrypted and decrypted without losing their authenticated final frame', function () {
    configureVaultFileEncryptionTest();
    $plaintextPath = tempnam(sys_get_temp_dir(), 'teamate-plaintext-');
    $encryptedPath = tempnam(sys_get_temp_dir(), 'teamate-encrypted-');
    file_put_contents($plaintextPath, '');

    try {
        $metadata = app(VaultFileEncryption::class)->encryptFile($plaintextPath, $encryptedPath);
        $encryptedStream = fopen($encryptedPath, 'rb');
        $decryptedStream = fopen('php://temp', 'w+b');

        app(VaultFileEncryption::class)->decryptStream($encryptedStream, $decryptedStream, $metadata['key_ref']);

        rewind($decryptedStream);
        expect(stream_get_contents($decryptedStream))->toBe('');
    } finally {
        if (is_resource($encryptedStream ?? null)) {
            fclose($encryptedStream);
        }

        if (is_resource($decryptedStream ?? null)) {
            fclose($decryptedStream);
        }

        unlink($plaintextPath);
        unlink($encryptedPath);
    }
});

test('tampered encrypted file content fails its integrity check', function () {
    configureVaultFileEncryptionTest();
    $plaintextPath = tempnam(sys_get_temp_dir(), 'teamate-plaintext-');
    $encryptedPath = tempnam(sys_get_temp_dir(), 'teamate-encrypted-');
    $plaintext = 'tamper detection content';
    file_put_contents($plaintextPath, $plaintext);

    try {
        $metadata = app(VaultFileEncryption::class)->encryptFile($plaintextPath, $encryptedPath);
        $encryptedContents = file_get_contents($encryptedPath);
        $lastByte = strlen($encryptedContents) - 1;
        $encryptedContents[$lastByte] = chr(ord($encryptedContents[$lastByte]) ^ 1);
        file_put_contents($encryptedPath, $encryptedContents);
        $encryptedStream = fopen($encryptedPath, 'rb');
        $decryptedStream = fopen('php://temp', 'w+b');

        expect(fn () => app(VaultFileEncryption::class)->decryptStream(
            $encryptedStream,
            $decryptedStream,
            $metadata['key_ref'],
        ))->toThrow(EncryptedFileException::class);
    } finally {
        if (is_resource($encryptedStream ?? null)) {
            fclose($encryptedStream);
        }

        if (is_resource($decryptedStream ?? null)) {
            fclose($decryptedStream);
        }

        unlink($plaintextPath);
        unlink($encryptedPath);
    }
});

test('file encryption fails explicitly when Vault Transit is unavailable', function () {
    config([
        'services.vault.address' => 'http://vault.test',
        'services.vault.token' => 'test-vault-token',
        'services.vault.transit_key' => 'teamate-files',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://vault.test/v1/transit/datakey/plaintext/teamate-files' => Http::response([], 503),
    ]);
    $plaintextPath = tempnam(sys_get_temp_dir(), 'teamate-plaintext-');
    $encryptedPath = tempnam(sys_get_temp_dir(), 'teamate-encrypted-');
    file_put_contents($plaintextPath, 'private file content');

    try {
        expect(fn () => app(VaultFileEncryption::class)->encryptFile($plaintextPath, $encryptedPath))
            ->toThrow(VaultTransitException::class);
    } finally {
        unlink($plaintextPath);
        unlink($encryptedPath);
    }
});
