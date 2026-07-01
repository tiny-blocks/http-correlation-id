<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\CorrelationId\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationId;

final class CorrelatedLoggerTest extends TestCase
{
    public function testPreservesExistingContextKeysWhenAttachingCorrelationId(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a correlation ID value */
        $correlationId = UuidCorrelationId::from(value: 'corr-xyz');

        /** @And a request carrying that correlation ID as an attribute */
        $request = new ServerRequest('GET', '/')->withAttribute('correlationId', $correlationId);

        /** @When the resolved logger emits a log entry with additional context */
        CorrelatedLogger::from($logger)->resolve($request)->info('order placed', ['order_id' => 42]);

        /** @Then both the original context and the correlation ID are recorded */
        $entries = $logger->loggedEntries();

        self::assertCount(1, $entries);
        self::assertSame(42, $entries[0]['context']['order_id']);
        self::assertSame('corr-xyz', $entries[0]['context']['correlation_id']);
    }

    public function testReturnsUnderlyingLoggerWhenRequestHasNoCorrelationIdAttribute(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a request without the correlationId attribute */
        $request = new ServerRequest('GET', '/');

        /** @When resolving a logger for that request */
        $resolved = CorrelatedLogger::from($logger)->resolve($request);

        /** @Then the underlying logger is returned unchanged */
        self::assertSame($logger, $resolved);
    }

    public function testAttachesCorrelationIdToLogContextWhenAttributeIsACorrelationId(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a correlation ID value */
        $correlationId = UuidCorrelationId::from(value: 'corr-123');

        /** @And a request carrying that correlation ID as an attribute */
        $request = new ServerRequest('GET', '/')->withAttribute('correlationId', $correlationId);

        /** @When the resolved logger emits a log entry */
        CorrelatedLogger::from($logger)->resolve($request)->info('something happened');

        /** @Then the recorded entry carries the correlation ID in its context */
        $entries = $logger->loggedEntries();

        self::assertCount(1, $entries);
        self::assertSame(LogLevel::INFO, $entries[0]['level']);
        self::assertSame('something happened', $entries[0]['message']);
        self::assertSame('corr-123', $entries[0]['context']['correlation_id']);
    }

    public function testReturnsUnderlyingLoggerWhenCorrelationIdAttributeIsNotACorrelationId(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a request whose correlationId attribute is a plain string */
        $request = new ServerRequest('GET', '/')->withAttribute('correlationId', 'not-a-correlation-id');

        /** @When resolving a logger for that request */
        $resolved = CorrelatedLogger::from($logger)->resolve($request);

        /** @Then the underlying logger is returned unchanged */
        self::assertSame($logger, $resolved);
    }
}
