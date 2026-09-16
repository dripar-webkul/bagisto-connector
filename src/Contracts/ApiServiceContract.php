<?php

declare(strict_types=1);

namespace Webkul\Bagisto\Contracts;

interface ApiServiceContract
{
    public function toRequest(string $method, string $endpoint, array $payload = [], array $options = []): array;
}
