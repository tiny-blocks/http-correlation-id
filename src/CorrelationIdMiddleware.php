<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\CorrelationId\Internal\RequestScopedCorrelationId;
use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationId;
use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationIdProvider;

/**
 * PSR-15 middleware that reads or generates a correlation identifier, exposes it as a request attribute
 * and through {@see CorrelationIdMiddleware::correlationId()}, and echoes it back through the
 * <code>Correlation-Id</code> response header for downstream tracing.
 */
final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    /**
     * Name of the request attribute carrying the resolved {@see CorrelationId}.
     */
    public const string ATTRIBUTE_NAME = 'correlationId';

    /**
     * Name of the header carrying the correlation ID on requests and responses. Use it when
     * propagating the identifier to outbound requests, so the name never drifts across services.
     */
    public const string HEADER_NAME = 'Correlation-Id';

    private function __construct(
        private CorrelationIdProvider $provider,
        private RequestScopedCorrelationId $correlationId
    ) {
    }

    /**
     * Builds a CorrelationIdMiddleware from an optional correlation ID provider.
     *
     * @param CorrelationIdProvider $provider The strategy used to generate a new correlation ID when the
     *                                        request carries none. Defaults to a UUID v7 provider.
     * @return CorrelationIdMiddleware The configured middleware instance.
     */
    public static function build(
        CorrelationIdProvider $provider = new UuidCorrelationIdProvider()
    ): CorrelationIdMiddleware {
        return new CorrelationIdMiddleware(provider: $provider, correlationId: new RequestScopedCorrelationId());
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $headerValue = trim($request->getHeaderLine(self::HEADER_NAME));
        $correlationId = $headerValue !== ''
            ? UuidCorrelationId::from(value: $headerValue)
            : $this->provider->generate();

        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $correlationId);
        $this->correlationId->assign(correlationId: $correlationId);

        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER_NAME, $correlationId->toString());
    }

    /**
     * Returns the correlation ID of the request in flight.
     *
     * <p>The returned instance is stable across the middleware's lifetime and reflects, at read time,
     * the identifier resolved for the request being handled. Share it with outbound boundaries (HTTP
     * clients, message payloads, loggers) to propagate the identifier. It reads as an empty string
     * before any request was handled.</p>
     *
     * @return CorrelationId The correlation ID of the request in flight.
     */
    public function correlationId(): CorrelationId
    {
        return $this->correlationId;
    }
}
