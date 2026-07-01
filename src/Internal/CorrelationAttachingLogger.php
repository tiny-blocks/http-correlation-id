<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId\Internal;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

final class CorrelationAttachingLogger extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $correlationId
    ) {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, [...$context, 'correlation_id' => $this->correlationId]);
    }
}
