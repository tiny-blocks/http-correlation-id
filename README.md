# Http Correlation Id

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/http-correlation-id/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Wiring the middleware](#wiring-the-middleware)
    + [Configuring a custom provider](#configuring-a-custom-provider)
    + [Propagating to outbound boundaries](#propagating-to-outbound-boundaries)
    + [Emitting correlated logs](#emitting-correlated-logs)
* [Concurrency model](#concurrency-model)
* [License](#license)
* [Contributing](#contributing)

## Overview

Provides a PSR-15 middleware that guarantees every inbound HTTP request carries a correlation identifier. When the
incoming request already includes a `Correlation-Id` header, the value is reused. Otherwise, a new identifier is
generated through a configurable provider (UUID v7 by default). The correlation ID is exposed as the request
attribute `correlationId`, is available through `CorrelationIdMiddleware::correlationId()` for outbound
propagation, and is echoed back through the `Correlation-Id` response header for end-to-end tracing across
services.

The library also ships `CorrelatedLogger`, a PSR-3 `LoggerInterface` decorator that reads the correlation ID at
each log write and attaches it to the log context under the `correlation_id` key. This produces structured logs
that can be grouped and filtered by the correlation ID without any extra plumbing in the consumer's log calls.

The header name is published as `CorrelationIdMiddleware::HEADER_NAME`, so outbound propagation never spells it
by hand. Pair it with a header-setting PSR-18 decorator (for example `HeaderSettingClient` from
`tiny-blocks/http`) to carry the identifier into every downstream HTTP call.

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
$middleware = CorrelationIdMiddleware::build();

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
$middleware = CorrelationIdMiddleware::build(provider: $provider);
```

### Propagating to outbound boundaries

`CorrelationIdMiddleware::correlationId()` returns the correlation ID of the request in flight. The instance is
stable, so it can be injected once into outbound boundaries (HTTP clients, message payloads), and its `toString()`
reflects, at read time, the identifier the middleware resolved for the request being handled. It reads as an
empty string before any request was handled.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;

$middleware = CorrelationIdMiddleware::build();

# Inject into outbound clients, message publishers, or bind in the DI container.
$correlationId = $middleware->correlationId();

# Anywhere downstream, while a request is being handled.
$correlationId->toString();
```

For outbound HTTP calls, pair the identifier with a header-setting PSR-18 decorator, such as
`HeaderSettingClient` from `tiny-blocks/http`. The header name comes from
`CorrelationIdMiddleware::HEADER_NAME`, so it never drifts across services, and a value resolving to an empty
string (boot, workers) leaves the request untouched.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\Client\HeaderSettingClient;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;

$middleware = CorrelationIdMiddleware::build();
$correlationId = $middleware->correlationId();

# Decorate any PSR-18 client once, at wiring time.
$client = HeaderSettingClient::with(client: $psr18Client, headerValues: [
    CorrelationIdMiddleware::HEADER_NAME => static fn(): string => $correlationId->toString()
]);

# Anywhere downstream, the outbound request carries the Correlation-Id header.
$client->sendRequest($request);
```

### Emitting correlated logs

Decorates any PSR-3 logger once, at wiring time, with the correlation ID of the request in flight. Every log entry
emitted anywhere in the application automatically carries the `correlation_id` context key, and entries emitted
outside a request (boot, workers) simply omit the key.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;

$middleware = CorrelationIdMiddleware::build();

# Decorate the application logger once, at wiring time.
$logger = CorrelatedLogger::from(logger: $applicationLogger, correlationId: $middleware->correlationId());

# Anywhere downstream, the entry carries the correlation_id context key.
$logger->info('Order placed.', ['order_id' => 42]);
```

## Concurrency model

`correlationId()` holds one value per middleware instance, which means one value per PHP process. This is correct
on process-per-request runtimes (PHP-FPM, Apache mod_php), where a process handles a single request at a time. On
long-running concurrent runtimes handling several requests in one process (Swoole, RoadRunner worker mode), use
the request attribute `correlationId` (name available as `CorrelationIdMiddleware::ATTRIBUTE_NAME`), which is
isolated per request by construction.

## License

Http Correlation Id is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
