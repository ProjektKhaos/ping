<?php

declare(strict_types=1);

namespace PingFloodWatch;

class HttpClient
{
    /** @return array<string,mixed>|array<int,mixed> */
    public function getJson(string $url, int $connectTimeout, int $timeout, int $maxBytes): array
    {
        return $this->requestJson($url, $connectTimeout, $timeout, $maxBytes, ['Accept: application/json']);
    }

    /** @param list<string> $headers @return array<string,mixed>|array<int,mixed> */
    public function getJsonWithHeaders(string $url, int $connectTimeout, int $timeout, int $maxBytes, array $headers): array
    {
        foreach ($headers as $header) {
            if (preg_match('/[\r\n]/', $header)) {
                throw new \InvalidArgumentException('HTTP headers must not contain line breaks.');
            }
        }
        array_unshift($headers, 'Accept: application/json');
        return $this->requestJson($url, $connectTimeout, $timeout, $maxBytes, $headers);
    }

    /** @param list<string> $headers @return array<string,mixed>|array<int,mixed> */
    private function requestJson(string $url, int $connectTimeout, int $timeout, int $maxBytes, array $headers): array
    {
        $body = '';
        $tooLarge = false;
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Unable to initialize HTTP client.');
        }

        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'PingFloodWatch/1.2.3 (+https://ping.aberg.online/)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($tooLarge) {
            throw new \RuntimeException('Provider response exceeded the configured size limit.');
        }
        if ($ok === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('Provider request failed (HTTP %d): %s', $status, $error ?: 'unexpected response'));
        }
        if ($contentType !== '' && !str_contains(strtolower($contentType), 'json')) {
            throw new \RuntimeException('Provider did not return JSON.');
        }

        $decoded = json_decode($body, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Provider JSON did not contain an array or object.');
        }

        return $decoded;
    }
}
