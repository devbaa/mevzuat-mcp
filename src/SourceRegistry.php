<?php

declare(strict_types=1);

namespace App;

final class SourceRegistry
{
    /** @var array<string,mixed> */
    private array $sources;

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function get(string $sourceKey): array
    {
        if (!isset($this->sources[$sourceKey])) {
            throw new \InvalidArgumentException("Unknown source: {$sourceKey}");
        }

        return $this->sources[$sourceKey];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->sources;
    }
}
