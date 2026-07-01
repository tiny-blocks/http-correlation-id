<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\CorrelationId\Unit;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\CorrelationId\CorrelationId;

final class CapturingHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $capturedRequest = null;

    public function __construct(private readonly ResponseInterface $response = new Response())
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->capturedRequest = $request;
        return $this->response;
    }

    public function wasInvoked(): bool
    {
        return !is_null($this->capturedRequest);
    }

    public function capturedBodyContents(): string
    {
        if (is_null($this->capturedRequest)) {
            return '';
        }

        return $this->capturedRequest->getBody()->getContents();
    }

    public function capturedCorrelationId(): ?CorrelationId
    {
        $attribute = $this->capturedRequest?->getAttribute('correlationId');
        return $attribute instanceof CorrelationId ? $attribute : null;
    }
}
