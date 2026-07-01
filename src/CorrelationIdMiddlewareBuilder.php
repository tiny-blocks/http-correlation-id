<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationIdProvider;

/**
 * Fluent builder that assembles a CorrelationIdMiddleware with an optional custom provider.
 */
final class CorrelationIdMiddlewareBuilder
{
    private ?CorrelationIdProvider $provider = null;

    /**
     * Builds a CorrelationIdMiddleware from the configured provider, or the default UUID v7 provider.
     *
     * @return CorrelationIdMiddleware The configured middleware instance.
     */
    public function build(): CorrelationIdMiddleware
    {
        return CorrelationIdMiddleware::build(
            provider: ($this->provider ?? new UuidCorrelationIdProvider())
        );
    }

    /**
     * Sets the provider used to generate a correlation ID and returns the builder.
     *
     * @param CorrelationIdProvider $provider The strategy used to generate a new correlation ID.
     * @return CorrelationIdMiddlewareBuilder The builder instance for fluent configuration.
     */
    public function withProvider(CorrelationIdProvider $provider): CorrelationIdMiddlewareBuilder
    {
        $this->provider = $provider;
        return $this;
    }
}
