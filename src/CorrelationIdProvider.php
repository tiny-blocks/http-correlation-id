<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

/**
 * Defines the strategy for generating a new correlation ID when one is not present in the incoming request.
 */
interface CorrelationIdProvider
{
    /**
     * Generates a new correlation ID.
     *
     * @return CorrelationId A newly generated correlation ID.
     */
    public function generate(): CorrelationId;
}
