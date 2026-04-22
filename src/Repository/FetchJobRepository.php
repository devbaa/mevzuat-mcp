<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class FetchJobRepository
{
    public function __construct(private PDO $pdo) {}

    public function createJob(array $row): int
    {
        $sql = 'INSERT INTO fetch_jobs (source_id, started_at, status, requested_by, throttle_profile, max_pages, max_items, notes) VALUES (:source_id, NOW(), :status, :requested_by, :throttle_profile, :max_pages, :max_items, :notes)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);
        return (int) $this->pdo->lastInsertId();
    }

    public function markFinished(int $jobId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE fetch_jobs SET status = :status, finished_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $jobId]);
    }

    public function insertFetchRequest(array $row): int
    {
        $sql = 'INSERT INTO fetch_requests (job_id, source_id, endpoint_name, request_params_json, page_number, cursor_value, retry_count, http_status, response_time_ms, throttled, created_at) VALUES (:job_id, :source_id, :endpoint_name, :request_params_json, :page_number, :cursor_value, :retry_count, :http_status, :response_time_ms, :throttled, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);
        return (int) $this->pdo->lastInsertId();
    }
}
