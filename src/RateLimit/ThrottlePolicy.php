<?php

declare(strict_types=1);

namespace App\RateLimit;

final class ThrottlePolicy
{
    public function __construct(
        public readonly int $minDelayMs,
        public readonly int $maxRpm,
        public readonly int $maxRph,
        public readonly int $concurrency,
        public readonly int $jitterMinMs,
        public readonly int $jitterMaxMs,
    ) {}

    public function sleepMicros(): int
    {
        $jitter = random_int($this->jitterMinMs, $this->jitterMaxMs);
        return ($this->minDelayMs + $jitter) * 1000;
    }
}
