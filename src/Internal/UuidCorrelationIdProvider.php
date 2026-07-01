<?php

declare(strict_types=1);

namespace TinyBlocks\Http\CorrelationId\Internal;

use Ramsey\Uuid\Uuid;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdProvider;

final readonly class UuidCorrelationIdProvider implements CorrelationIdProvider
{
    public function generate(): CorrelationId
    {
        return UuidCorrelationId::from(value: Uuid::uuid7()->toString());
    }
}
