<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DocumentRepository
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $row */
    public function upsertDocument(array $row): int
    {
        $identity = $row['identity'];

        $sql = <<<SQL
INSERT INTO documents (
    source_id, source_tool, source_variant, fetch_transport,
    used_fallback, used_cache, semantic_related, full_download_completed,
    document_type, tertip, no, madde_no, raw_identifier,
    title, content_text, content_hash, fetch_status,
    first_seen_at, last_seen_at, last_job_id
) VALUES (
    :source_id, :source_tool, :source_variant, :fetch_transport,
    :used_fallback, :used_cache, :semantic_related, :full_download_completed,
    :document_type, :tertip, :no, :madde_no, :raw_identifier,
    :title, :content_text, :content_hash, :fetch_status,
    NOW(), NOW(), :last_job_id
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content_text = VALUES(content_text),
    content_hash = VALUES(content_hash),
    fetch_status = VALUES(fetch_status),
    last_seen_at = NOW(),
    last_job_id = VALUES(last_job_id),
    source_tool = VALUES(source_tool),
    source_variant = VALUES(source_variant),
    fetch_transport = VALUES(fetch_transport),
    used_fallback = VALUES(used_fallback),
    used_cache = VALUES(used_cache),
    semantic_related = VALUES(semantic_related),
    full_download_completed = VALUES(full_download_completed)
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'source_id' => $row['source_id'],
            'source_tool' => $row['source_tool'],
            'source_variant' => $row['source_variant'],
            'fetch_transport' => $row['fetch_transport'],
            'used_fallback' => $row['used_fallback'],
            'used_cache' => $row['used_cache'],
            'semantic_related' => $row['semantic_related'],
            'full_download_completed' => $row['full_download_completed'],
            'document_type' => $identity['document_type'],
            'tertip' => $identity['tertip'],
            'no' => $identity['no'],
            'madde_no' => $identity['madde_no'],
            'raw_identifier' => $identity['raw_identifier'],
            'title' => $row['title'],
            'content_text' => $row['content_text'],
            'content_hash' => $row['content_hash'],
            'fetch_status' => $row['fetch_status'],
            'last_job_id' => $row['last_job_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
