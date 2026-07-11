<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\CorrelationId\Unit;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\CorrelationId\CorrelationIdProvider;
use TinyBlocks\Http\CorrelationId\Internal\UuidCorrelationId;

final class CorrelationIdMiddlewareTest extends TestCase
{
    public function testUsesCustomProvider(): void
    {
        /** @Given a fixed value the custom provider will return */
        $fixedValue = 'custom-fixed-id-999';

        /** @And a custom provider that returns that fixed correlation ID */
        $customProvider = new readonly class ($fixedValue) implements CorrelationIdProvider {
            public function __construct(private string $value)
            {
            }

            public function generate(): CorrelationId
            {
                return UuidCorrelationId::from(value: $this->value);
            }
        };

        /** @And a middleware configured with the custom provider */
        $middleware = CorrelationIdMiddleware::build(provider: $customProvider);

        /** @And a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the custom provider value should be used */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertSame($fixedValue, $correlationId->toString());

        /** @And the response header should contain the custom value */
        self::assertSame($fixedValue, $response->getHeaderLine('Correlation-Id'));
    }

    public function testCorrelationIdStartsEmptyBeforeAnyRequestIsHandled(): void
    {
        /** @Given a middleware no request has gone through yet */
        $middleware = CorrelationIdMiddleware::build();

        /** @When the correlation ID of the request in flight is read */
        $value = $middleware->correlationId()->toString();

        /** @Then the value should be empty */
        self::assertSame('', $value);
    }

    public function testCorrelationIdExposesTheValueResolvedFromTheRequestHeader(): void
    {
        /** @Given an existing correlation ID carried in the request header */
        $existingCorrelationId = 'req-in-flight-42';

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @When the middleware processes a request with that Correlation-Id header */
        $middleware->process(
            new ServerRequest('GET', '/', ['Correlation-Id' => $existingCorrelationId]),
            new CapturingHandler()
        );

        /** @Then the correlation ID of the request in flight should expose the header value */
        self::assertSame($existingCorrelationId, $middleware->correlationId()->toString());
    }

    public function testCorrelationIdIsAssignedBeforeTheHandlerRuns(): void
    {
        /** @Given a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that records the correlation ID of the request in flight at handling time */
        $handler = new readonly class ($middleware->correlationId()) implements RequestHandlerInterface {
            public function __construct(private CorrelationId $correlationId)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(status: 200, headers: ['Seen-By-Handler' => $this->correlationId->toString()]);
            }
        };

        /** @When the middleware processes a request without the Correlation-Id header */
        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        /** @Then the handler should have observed the correlation ID already assigned */
        self::assertNotSame('', $response->getHeaderLine('Seen-By-Handler'));
        self::assertSame($response->getHeaderLine('Correlation-Id'), $response->getHeaderLine('Seen-By-Handler'));
    }

    public function testCorrelationIdReflectsTheLatestHandledRequest(): void
    {
        /** @Given a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @When the middleware processes two requests carrying different Correlation-Id headers */
        $middleware->process(
            new ServerRequest('GET', '/first', ['Correlation-Id' => 'req-first']),
            new CapturingHandler()
        );
        $middleware->process(
            new ServerRequest('GET', '/second', ['Correlation-Id' => 'req-second']),
            new CapturingHandler()
        );

        /** @Then the correlation ID of the request in flight should expose the latest value */
        self::assertSame('req-second', $middleware->correlationId()->toString());
    }

    public function testPreservesExistingResponseHeaders(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that returns a response with an existing custom header */
        $handler = new CapturingHandler(
            response: new Response(
                status: 200,
                headers: ['Custom-Header' => 'custom-value']
            )
        );

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the existing response header should be preserved */
        self::assertSame('custom-value', $response->getHeaderLine('Custom-Header'));

        /** @And the Correlation-Id header should also be present */
        self::assertTrue($response->hasHeader('Correlation-Id'));
    }

    public function testReusesCorrelationIdWhenHeaderIsPresent(): void
    {
        /** @Given an existing correlation ID carried in the request header */
        $existingCorrelationId = 'req-abc-123';

        /** @And a request with that Correlation-Id header */
        $request = new ServerRequest('GET', '/', ['Correlation-Id' => $existingCorrelationId]);

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the existing correlation ID should be preserved in the request attribute */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertSame($existingCorrelationId, $correlationId->toString());

        /** @And the response should contain the same Correlation-Id header */
        self::assertSame($existingCorrelationId, $response->getHeaderLine('Correlation-Id'));
    }

    public function testEachRequestGeneratesUniqueCorrelationId(): void
    {
        /** @Given a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a first request without the Correlation-Id header */
        $firstRequest = new ServerRequest('GET', '/first');

        /** @And a second request without the Correlation-Id header */
        $secondRequest = new ServerRequest('GET', '/second');

        /** @And a handler for the first request */
        $firstHandler = new CapturingHandler();

        /** @And a handler for the second request */
        $secondHandler = new CapturingHandler();

        /** @When the middleware processes the first request */
        $middleware->process($firstRequest, $firstHandler);

        /** @And the middleware processes the second request */
        $middleware->process($secondRequest, $secondHandler);

        /** @Then each request should have received a different correlation ID */
        $firstCorrelationId = $firstHandler->capturedCorrelationId();
        $secondCorrelationId = $secondHandler->capturedCorrelationId();

        self::assertNotNull($firstCorrelationId);
        self::assertNotNull($secondCorrelationId);
        self::assertNotSame($firstCorrelationId->toString(), $secondCorrelationId->toString());
    }

    public function testGeneratedCorrelationIdMatchesUuidV7Format(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the generated value should match the UUID v7 format */
        $correlationId = $handler->capturedCorrelationId();
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        self::assertNotNull($correlationId);
        self::assertMatchesRegularExpression($uuidPattern, $correlationId->toString());
    }

    public function testGeneratesCorrelationIdWhenHeaderIsMissing(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then a correlation ID should be generated and set as a request attribute */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertNotEmpty($correlationId->toString());

        /** @And the response should contain the generated Correlation-Id header */
        self::assertTrue($response->hasHeader('Correlation-Id'));
        self::assertSame($correlationId->toString(), $response->getHeaderLine('Correlation-Id'));
    }

    public function testGeneratesNewCorrelationIdWhenHeaderIsEmpty(): void
    {
        /** @Given a request with an empty Correlation-Id header */
        $request = new ServerRequest('GET', '/', ['Correlation-Id' => '']);

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then a new correlation ID should be generated */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertNotEmpty($correlationId->toString());

        /** @And the response should contain the generated Correlation-Id */
        self::assertTrue($response->hasHeader('Correlation-Id'));
        self::assertSame($correlationId->toString(), $response->getHeaderLine('Correlation-Id'));
    }

    public function testCorrelationIdAttributeIsAccessibleDownstream(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the attribute value should match the response header */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertSame($response->getHeaderLine('Correlation-Id'), $correlationId->toString());
    }

    public function testGeneratesNewCorrelationIdWhenHeaderIsWhitespaceOnly(): void
    {
        /** @Given the whitespace-only value that will be returned for the Correlation-Id header */
        $whitespaceValue = '   ';

        /** @And a real request used as a fallback for other header lookups */
        $request = new ServerRequest('GET', '/');

        /** @And a stub request that returns whitespace for the Correlation-Id header */
        $stubbedRequest = $this->createStub(ServerRequestInterface::class);

        /** @And the stub is configured to return the whitespace value for the Correlation-Id header */
        $stubbedRequest->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use ($whitespaceValue, $request): string {
                if (strcasecmp($name, 'Correlation-Id') === 0) {
                    return $whitespaceValue;
                }
                return $request->getHeaderLine($name);
            });

        /** @And the stub is configured to delegate withAttribute to the real request */
        $stubbedRequest->method('withAttribute')
            ->willReturnCallback(function (string $name, mixed $value) use ($request): ServerRequestInterface {
                return $request->withAttribute($name, $value);
            });

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($stubbedRequest, $handler);

        /** @Then a new correlation ID should be generated instead of using the whitespace */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertNotSame($whitespaceValue, $correlationId->toString());
        self::assertNotEmpty($correlationId->toString());

        /** @And the response should contain the generated Correlation-Id */
        self::assertTrue($response->hasHeader('Correlation-Id'));
        self::assertSame($correlationId->toString(), $response->getHeaderLine('Correlation-Id'));
    }
}
