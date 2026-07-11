<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * PSR-3 logger that attaches the correlation ID of the request in flight to every log context
 * under the <code>correlation_id</code> key.
 */
final class CorrelatedLogger extends AbstractLogger
{
    private function __construct(
        private readonly LoggerInterface $logger,
        private readonly CorrelationId $correlationId
    ) {
    }

    /**
     * Creates a CorrelatedLogger from a PSR-3 logger and a correlation ID.
     *
     * @param LoggerInterface $logger The underlying logger to delegate writes to.
     * @param CorrelationId $correlationId The correlation ID read at each log write, typically the one
     *                                     exposed by {@see CorrelationIdMiddleware::correlationId()}.
     * @return CorrelatedLogger The created instance.
     */
    public static function from(LoggerInterface $logger, CorrelationId $correlationId): CorrelatedLogger
    {
        return new CorrelatedLogger(logger: $logger, correlationId: $correlationId);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $correlationId = $this->correlationId->toString();

        if ($correlationId === '') {
            $this->logger->log($level, $message, $context);
            return;
        }

        $this->logger->log($level, $message, [...$context, 'correlation_id' => $correlationId]);
    }
}
