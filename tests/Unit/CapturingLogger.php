<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\CorrelationId\Unit;

use Psr\Log\AbstractLogger;
use Stringable;

final class CapturingLogger extends AbstractLogger
{
    private array $entries = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->entries[] = [
            'level'   => $level,
            'message' => (string) $message,
            'context' => $context
        ];
    }

    public function loggedEntries(): array
    {
        return $this->entries;
    }
}
