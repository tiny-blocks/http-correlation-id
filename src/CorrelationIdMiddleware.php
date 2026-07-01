<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationId;

/**
 * PSR-15 middleware that reads or generates a correlation identifier, exposes it as a request attribute,
 * and echoes it back through the <code>Correlation-Id</code> response header for downstream tracing.
 */
final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    private const string HEADER_NAME = 'Correlation-Id';
    private const string ATTRIBUTE_NAME = 'correlationId';

    private function __construct(private CorrelationIdProvider $provider)
    {
    }

    /**
     * Builds a CorrelationIdMiddleware from a correlation ID provider.
     *
     * @param CorrelationIdProvider $provider The strategy used to generate a new correlation ID when absent.
     * @return CorrelationIdMiddleware The configured middleware instance.
     */
    public static function build(CorrelationIdProvider $provider): CorrelationIdMiddleware
    {
        return new CorrelationIdMiddleware(provider: $provider);
    }

    /**
     * Creates a CorrelationIdMiddlewareBuilder for fluent configuration.
     *
     * @return CorrelationIdMiddlewareBuilder The builder instance.
     */
    public static function create(): CorrelationIdMiddlewareBuilder
    {
        return new CorrelationIdMiddlewareBuilder();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $headerValue = trim($request->getHeaderLine(self::HEADER_NAME));
        $correlationId = $headerValue !== ''
            ? UuidCorrelationId::from(value: $headerValue)
            : $this->provider->generate();

        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $correlationId);
        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER_NAME, $correlationId->toString());
    }
}
