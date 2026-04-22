<?php

declare(strict_types=1);

namespace App\Service;

use App\Downloader\AbstractSourceDownloader;
use App\Logger\ImportLogger;
use App\Repository\FetchJobRepository;

final class FetchJobRunner
{
    public function __construct(
        private FetchJobRepository $fetchJobRepository,
        private ImportLogger $logger,
    ) {}

    /** @param array<string,mixed> $jobRow */
    public function run(AbstractSourceDownloader $downloader, array $jobRow): void
    {
        $jobId = $this->fetchJobRepository->createJob($jobRow);
        $this->logger->info('Import job started', ['job_id' => $jobId, 'source_id' => $jobRow['source_id']]);

        try {
            $jobContext = $jobRow + ['job_id' => $jobId];
            $downloader->run($jobContext);
            $this->fetchJobRepository->markFinished($jobId, 'completed');
            $this->logger->info('Import job completed', ['job_id' => $jobId]);
        } catch (\Throwable $e) {
            $this->fetchJobRepository->markFinished($jobId, 'failed');
            $this->logger->error('Import job failed', ['job_id' => $jobId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
