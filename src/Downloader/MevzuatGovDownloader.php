<?php

declare(strict_types=1);

namespace App\Downloader;

use App\RateLimit\ThrottlePolicy;

final class MevzuatGovDownloader extends AbstractSourceDownloader
{
    /** @param array<string,mixed> $jobContext */
    public function run(array $jobContext): void
    {
        $jobId = (int) $jobContext['job_id'];
        $sourceId = (int) $jobContext['source_id'];
        $maxPages = (int) ($jobContext['max_pages'] ?? 10);
        $pageSize = (int) ($jobContext['page_size'] ?? 25);
        $query = (string) ($jobContext['filters']['aranacak_ifade'] ?? '');

        $throttle = new ThrottlePolicy(1200, 30, 1200, 1, 150, 450);

        for ($page = 1; $page <= $maxPages; $page++) {
            $params = [
                'draw' => 1,
                'start' => ($page - 1) * $pageSize,
                'length' => $pageSize,
                'search' => ['value' => $query],
            ];

            $response = $this->httpClient->request(
                'POST',
                (string) $jobContext['search_url'],
                ['Content-Type' => 'application/json; charset=UTF-8'],
                json_encode($params),
                $throttle,
                0,
            );

            $requestId = $this->fetchJobRepository->insertFetchRequest([
                'job_id' => $jobId,
                'source_id' => $sourceId,
                'endpoint_name' => 'search_documents',
                'request_params_json' => json_encode($params),
                'page_number' => $page,
                'cursor_value' => null,
                'retry_count' => $response['retry_count'],
                'http_status' => $response['status'],
                'response_time_ms' => $response['duration_ms'],
                'throttled' => 1,
            ]);

            $decoded = json_decode((string) $response['body'], true);
            $rows = $decoded['data'] ?? [];
            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $identity = [
                    'document_type' => (string) ($row['mevzuatTur'] ?? ''),
                    'tertip' => (string) ($row['mevzuatTertip'] ?? ''),
                    'no' => (string) ($row['mevzuatNo'] ?? ''),
                    'madde_no' => null,
                    'raw_identifier' => sprintf('%s.%s.%s', $row['mevzuatTur'] ?? '', $row['mevzuatTertip'] ?? '', $row['mevzuatNo'] ?? ''),
                ];

                $contentText = (string) ($row['mevzuatAdi'] ?? '');
                $hash = hash('sha256', $contentText);

                $this->documentRepository->upsertDocument([
                    'source_id' => $sourceId,
                    'source_tool' => 'search_kanun',
                    'source_variant' => 'list',
                    'fetch_transport' => 'json',
                    'used_fallback' => 0,
                    'used_cache' => 0,
                    'semantic_related' => 0,
                    'full_download_completed' => 0,
                    'identity' => $identity,
                    'title' => (string) ($row['mevzuatAdi'] ?? ''),
                    'content_text' => $contentText,
                    'content_hash' => $hash,
                    'fetch_status' => 'listed',
                    'last_job_id' => $jobId,
                    'source_request_id' => $requestId,
                    'request_params_json' => json_encode($params),
                    'page_number' => $page,
                ]);
            }
        }
    }
}
