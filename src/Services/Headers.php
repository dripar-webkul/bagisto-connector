<?php

namespace Webkul\Bagisto\Services;

final class Headers
{
    private function __construct(private readonly array $headers) {}

    public static function create(): self
    {
        return new self([]);
    }

    public static function withAuthorization($token): self
    {
        return new self([
            'Authorization' => "Bearer {$token}",
        ]);
    }

    public function withContentType(string $contentType = 'application/json'): self
    {
        return new self([
            ...$this->headers,
            'accept'       => 'application/json',
            'Content-Type' => $contentType,
        ]);
    }

    public function toArray(): array
    {
        return $this->headers;
    }
}
