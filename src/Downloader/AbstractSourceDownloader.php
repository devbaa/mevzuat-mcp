<?php

declare(strict_types=1);

namespace App\Downloader;

use App\Http\HttpClient;
use App\Repository\DocumentRepository;
use App\Repository\FetchJobRepository;

abstract class AbstractSourceDownloader
{
    public function __construct(
        protected HttpClient $httpClient,
        protected DocumentRepository $documentRepository,
        protected FetchJobRepository $fetchJobRepository,
    ) {}

    /** @param array<string,mixed> $jobContext */
    abstract public function run(array $jobContext): void;

    /** @param array<string,mixed> $payload */
    protected function sha256(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
