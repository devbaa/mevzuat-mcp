<?php

declare(strict_types=1);

namespace App\Http;

use App\RateLimit\ThrottlePolicy;

final class HttpClient
{
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?ThrottlePolicy $policy = null,
        int $retryCount = 0,
    ): array {
        if ($policy !== null) {
            usleep($policy->sleepMicros());
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 45,
        ]);

        $started = microtime(true);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $durationMs = (int) ((microtime(true) - $started) * 1000);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => is_string($raw) ? $raw : '',
            'error' => $curlErr ?: null,
            'duration_ms' => $durationMs,
            'retry_count' => $retryCount,
        ];
    }

    private function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[] = sprintf('%s: %s', $k, $v);
        }

        return $out;
    }
}
