<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId;

/**
 * Represents an immutable correlation identifier used to trace a request across service boundaries.
 */
interface CorrelationId
{
    /**
     * Returns the string representation of the correlation ID.
     *
     * @return string The correlation ID value.
     */
    public function toString(): string;
}
