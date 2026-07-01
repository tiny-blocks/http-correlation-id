<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\CorrelationId\Internal\CorrelationAttachingLogger;

/**
 * Wraps a PSR-3 logger so that log entries emitted during request handling automatically carry
 * the request's correlation identifier in the log context.
 */
final readonly class CorrelatedLogger
{
    private function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Creates a CorrelatedLogger from a PSR-3 logger.
     *
     * @param LoggerInterface $logger The underlying logger to delegate writes to.
     * @return CorrelatedLogger The created instance.
     */
    public static function from(LoggerInterface $logger): CorrelatedLogger
    {
        return new CorrelatedLogger(logger: $logger);
    }

    /**
     * Returns a PSR-3 logger that attaches the request's correlation ID to every log context.
     *
     * <p>When the request has no <code>correlationId</code> attribute, or the attribute is not a
     * {@see CorrelationId} instance, the underlying logger is returned unchanged.</p>
     *
     * @param ServerRequestInterface $request The request whose correlation ID should be attached.
     * @return LoggerInterface A logger that emits log entries carrying the correlation ID.
     */
    public function resolve(ServerRequestInterface $request): LoggerInterface
    {
        $correlationId = $request->getAttribute('correlationId');

        if (!$correlationId instanceof CorrelationId) {
            return $this->logger;
        }

        return new CorrelationAttachingLogger(
            logger: $this->logger,
            correlationId: $correlationId->toString()
        );
    }
}
