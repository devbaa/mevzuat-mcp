<?php

declare(strict_types=1);

namespace App\Logger;

final class ImportLogger
{
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "%s [%s] %s %s\n",
            date('c'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        file_put_contents(__DIR__ . '/../../storage/import.log', $line, FILE_APPEND);
    }
}
