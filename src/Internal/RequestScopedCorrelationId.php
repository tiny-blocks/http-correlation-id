<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId\Internal;

use TinyBlocks\Http\CorrelationId\CorrelationId;

final class RequestScopedCorrelationId implements CorrelationId
{
    private ?CorrelationId $correlationId = null;

    public function assign(CorrelationId $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function toString(): string
    {
        return $this->correlationId?->toString() ?? '';
    }
}
