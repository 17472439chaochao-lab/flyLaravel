<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpClient
{
    public static function request(string $method, string $url, array $options = [], int $retries = 2, int $sleep_ms = 200, int $timeout = 10): array
    {
        $method = strtoupper($method);
        $last = null;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $client = Http::timeout($timeout)->acceptJson();

                if (isset($options['headers']) && is_array($options['headers'])) {
                    $client = $client->withHeaders($options['headers']);
                }

                if (isset($options['query']) && is_array($options['query'])) {
                    $client = $client->withQueryParameters($options['query']);
                }

                $payload = $options['body'] ?? $options['json'] ?? [];
                if (isset($options['form']) && is_array($options['form'])) {
                    $response = $client->asForm()->send($method, $url, ['form_params' => $options['form']]);
                } else {
                    $response = $client->send($method, $url, $payload === [] ? [] : ['json' => $payload]);
                }

                return [
                    'ok' => $response->successful(),
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'error' => $response->successful() ? null : $response->body(),
                ];
            } catch (Throwable $exception) {
                $last = $exception;

                if ($attempt < $retries) {
                    usleep(max(0, $sleep_ms) * 1000);
                }
            }
        }

        return [
            'ok' => false,
            'status' => 0,
            'body' => null,
            'error' => $last?->getMessage() ?? 'request failed',
        ];
    }

    public static function get(string $url, array $query = [], array $headers = []): array
    {
        return self::request('GET', $url, compact('query', 'headers'));
    }

    public static function post(string $url, array $body = [], array $headers = []): array
    {
        return self::request('POST', $url, compact('body', 'headers'));
    }
}