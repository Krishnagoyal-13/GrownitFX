<?php

declare(strict_types=1);

namespace App\MT5;

use RuntimeException;

final class HttpClient
{
    private $ch = null;

    public function __construct(private readonly string $baseUrl, private readonly string $cookieFile)
    {
        $cookieDir = dirname($cookieFile);
        if (!is_dir($cookieDir) && !mkdir($cookieDir, 0775, true) && !is_dir($cookieDir)) {
            throw new RuntimeException('Unable to create MT5 cookie directory.');
        }

        $this->ch = curl_init();
        if (!$this->ch) {
            throw new RuntimeException('Unable to initialize cURL for MT5 HTTP client.');
        }

        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Connection: Keep-Alive',
            ],
        ]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $query = [], ?array $body = null): array
    {
        return $this->request('POST', $path, $query, $body);
    }

    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (strtoupper($method) === 'POST') {
            $payload = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new RuntimeException('Failed to JSON encode request payload.');
            }
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
        }

        $response = curl_exec($this->ch);
        if ($response === false) {
            throw new RuntimeException('MT5 HTTP request failed: ' . curl_error($this->ch));
        }

        $status = (int) curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if ($status !== 200) {
            throw new RuntimeException('MT5 HTTP error ' . $status . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('MT5 returned invalid JSON response.');
        }

        return $decoded;
    }

    public function close(): void
    {
        if (is_resource($this->ch) || $this->ch instanceof \CurlHandle) {
            curl_close($this->ch);
        }
        $this->ch = null;
    }
}
