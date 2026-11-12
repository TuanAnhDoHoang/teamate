<?php

namespace App\Services;

use App\Exceptions\ClamAvScanException;

class ClamAvScanner
{
    public function scan(string $path): ?string
    {
        $host = (string) config('services.clamav.host');
        $port = (int) config('services.clamav.port');
        $timeout = (int) config('services.clamav.timeout');
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
        );

        if (! is_resource($socket)) {
            throw new ClamAvScanException("Unable to connect to ClamAV ({$errorCode}): {$errorMessage}");
        }

        $file = fopen($path, 'rb');

        if (! is_resource($file)) {
            fclose($socket);

            throw new ClamAvScanException('Unable to open the uploaded file for scanning.');
        }

        try {
            if (! stream_set_timeout($socket, $timeout)) {
                throw new ClamAvScanException('Unable to set the ClamAV socket timeout.');
            }

            $this->write($socket, "zINSTREAM\0");

            while (! feof($file)) {
                $chunk = fread($file, 8192);

                if ($chunk === false) {
                    throw new ClamAvScanException('Unable to read the uploaded file for scanning.');
                }

                if ($chunk !== '') {
                    $this->write($socket, pack('N', strlen($chunk)).$chunk);
                }
            }

            $this->write($socket, pack('N', 0));
            $response = '';
            while (strlen($response) < 4096) {
                $character = fgetc($socket);

                if ($character === false || $character === "\0" || $character === "\n") {
                    break;
                }

                $response .= $character;
            }

            $socketMetadata = stream_get_meta_data($socket);

            if ($response === '' || $socketMetadata['timed_out']) {
                throw new ClamAvScanException('ClamAV did not return a scan result before the timeout.');
            }

            $response = trim($response, "\r\n ");

            if (preg_match('/^stream:\s*(.+)\s+FOUND$/i', $response, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/^stream:\s*OK$/i', $response) === 1) {
                return null;
            }

            throw new ClamAvScanException("ClamAV returned an unexpected response: {$response}");
        } finally {
            fclose($file);
            fclose($socket);
        }
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $data): void
    {
        while ($data !== '') {
            $written = fwrite($socket, $data);

            if ($written === false || $written === 0) {
                throw new ClamAvScanException('Unable to send the uploaded file to ClamAV.');
            }

            $data = substr($data, $written);
        }
    }
}
