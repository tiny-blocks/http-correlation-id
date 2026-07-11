<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\CorrelationId\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationId;

final class CorrelatedLoggerTest extends TestCase
{
    public function testAttachesCorrelationIdToLogContext(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a correlated logger reading a fixed correlation ID */
        $correlatedLogger = CorrelatedLogger::from(
            logger: $logger,
            correlationId: UuidCorrelationId::from(value: 'corr-123')
        );

        /** @When a log entry is emitted */
        $correlatedLogger->info('something happened');

        /** @Then the recorded entry carries the correlation ID in its context */
        $entries = $logger->loggedEntries();

        self::assertCount(1, $entries);
        self::assertSame(LogLevel::INFO, $entries[0]['level']);
        self::assertSame('something happened', $entries[0]['message']);
        self::assertSame('corr-123', $entries[0]['context']['correlation_id']);
    }

    public function testPreservesExistingContextKeysWhenAttachingCorrelationId(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a correlated logger reading a fixed correlation ID */
        $correlatedLogger = CorrelatedLogger::from(
            logger: $logger,
            correlationId: UuidCorrelationId::from(value: 'corr-xyz')
        );

        /** @When a log entry is emitted with additional context */
        $correlatedLogger->info('order placed', ['order_id' => 42]);

        /** @Then both the original context and the correlation ID are recorded */
        $entries = $logger->loggedEntries();

        self::assertCount(1, $entries);
        self::assertSame(42, $entries[0]['context']['order_id']);
        self::assertSame('corr-xyz', $entries[0]['context']['correlation_id']);
    }

    public function testOmitsCorrelationIdWhenTheValueIsEmpty(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a correlated logger reading the correlation ID of a middleware no request went through */
        $correlatedLogger = CorrelatedLogger::from(
            logger: $logger,
            correlationId: CorrelationIdMiddleware::build()->correlationId()
        );

        /** @When a log entry is emitted */
        $correlatedLogger->info('boot completed', ['component' => 'payment']);

        /** @Then the recorded entry carries the original context without a correlation ID key */
        $entries = $logger->loggedEntries();

        self::assertCount(1, $entries);
        self::assertIsArray($entries[0]['context']);
        self::assertSame('payment', $entries[0]['context']['component']);
        self::assertArrayNotHasKey('correlation_id', $entries[0]['context']);
    }

    public function testReadsTheCorrelationIdAtLogTime(): void
    {
        /** @Given an in-memory PSR-3 logger */
        $logger = new CapturingLogger();

        /** @And a middleware sharing its correlation ID with a correlated logger built before any request */
        $middleware = CorrelationIdMiddleware::build();
        $correlatedLogger = CorrelatedLogger::from(logger: $logger, correlationId: $middleware->correlationId());

        /** @And a request carrying a Correlation-Id header handled after the logger was built */
        $middleware->process(
            new ServerRequest('GET', '/', ['Correlation-Id' => 'corr-late-bound']),
            new CapturingHandler()
        );

        /** @When a log entry is emitted */
        $correlatedLogger->info('charge routed');

        /** @Then the recorded entry carries the correlation ID resolved after the logger was built */
        $entries = $logger->loggedEntries();

        self::assertCount(1, $entries);
        self::assertSame('corr-late-bound', $entries[0]['context']['correlation_id']);
    }
}
