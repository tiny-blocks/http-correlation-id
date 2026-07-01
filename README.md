# Http Correlation Id

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/http-correlation-id/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Wiring the middleware](#wiring-the-middleware)
    + [Configuring a custom provider](#configuring-a-custom-provider)
    + [Emitting correlated logs](#emitting-correlated-logs)
* [License](#license)
* [Contributing](#contributing)

## Overview

Provides a PSR-15 middleware that guarantees every inbound HTTP request carries a correlation identifier. When the
incoming request already includes a `Correlation-Id` header, the value is reused. Otherwise, a new identifier is
generated through a configurable provider (UUID v7 by default). The correlation ID is exposed as the request
attribute `correlationId` so downstream handlers can read it, and is echoed back through the `Correlation-Id`
response header for end-to-end tracing across services.

The library also ships `CorrelatedLogger`, a thin wrapper over any PSR-3 `LoggerInterface` that resolves the
correlation ID from the request and attaches it to the log context under the `correlation_id` key. This produces
structured logs that can be grouped and filtered by the correlation ID without any extra plumbing in the consumer's
log calls.

## Installation

```bash
composer require tiny-blocks/http-correlation-id
```

## How to use

### Wiring the middleware

Builds the middleware with the default UUID v7 provider and processes a request through it.

```php
<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;

# Build the middleware with the default UUID v7 provider.
$middleware = CorrelationIdMiddleware::create()->build();

# Process an inbound request through the middleware.
$response = $middleware->process(
    new ServerRequest('GET', '/orders'),
    new class () implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response();
        }
    }
);

# The response carries the generated correlation ID on the Correlation-Id header.
$response->getHeaderLine('Correlation-Id');
```

### Configuring a custom provider

Replaces the default UUID v7 provider with a custom strategy (for example, an externally supplied identifier).

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\CorrelationId\CorrelationIdProvider;

# Custom provider that returns a fixed correlation ID.
$provider = new readonly class () implements CorrelationIdProvider {
    public function generate(): CorrelationId
    {
        return new readonly class () implements CorrelationId {
            public function toString(): string
            {
                return 'fixed-correlation-id';
            }
        };
    }
};

# Build the middleware with the custom provider.
$middleware = CorrelationIdMiddleware::create()
    ->withProvider(provider: $provider)
    ->build();
```

### Emitting correlated logs

Uses `CorrelatedLogger` inside a downstream handler so that every log entry automatically carries the request's
correlation ID under the `correlation_id` context key.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;

final readonly class PlaceOrderHandler implements RequestHandlerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        # Resolve a PSR-3 logger that attaches the request's correlation ID to every log context.
        $logger = CorrelatedLogger::from($this->logger)->resolve($request);

        $logger->info('Order placed.', ['order_id' => 42]);

        # ...
    }
}
```

## License

Http Correlation Id is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
