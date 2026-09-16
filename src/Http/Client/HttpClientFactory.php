<?php

namespace Webkul\Bagisto\Http\Client;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Webkul\Bagisto\Enums\Services\ContentType;
use Webkul\Bagisto\Services\ApiService;
use Webkul\Bagisto\Services\Headers;

final class HttpClientFactory
{
    private ?string $baseUri = null;

    private ?string $email = null;

    private ?string $password = null;

    private array $headers = [];

    public function withEmail(string $email): self
    {
        $this->email = trim($email);

        return $this;
    }

    public function withPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function withBaseUri(string $baseUri): self
    {
        $this->baseUri = $baseUri;

        return $this;
    }

    public function withHttpHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function make(): ApiService
    {
        $headers = Headers::create();

        if ($this->email !== null && $this->password !== null) {
            $token = $this->apiAuth($this->email, $this->password);
            $headers = Headers::withAuthorization($token);
        }

        return new ApiService($this->baseUri, $headers);
    }

    public function apiAuth(string $email, string $password): string
    {
        $contentType = ContentType::JSON->value;

        $response = Http::withoutVerifying()->withHeaders([
            'Accept' => $contentType,
        ])->post("{$this->baseUri}/api/v1/admin/login", [
            'email'      => $email,
            'password'   => $password,
            'device_name'=> 'api',
        ]);

        if ($response->failed()) {
            if ($response->clientError()) {
                if ($response->status() == 422) {
                    $errorJson = $response->json();
                    throw ValidationException::withMessages($errorJson['errors'] ?? $errorJson);
                }

                if ($response->status() == 404) {
                    throw ValidationException::withMessages([
                        'shop_url' => 'Shop URL is invalid!',
                    ]);
                }

                if ($response->status() == 405) {
                    throw ValidationException::withMessages([
                        'shop_url' => 'The REST API is not installed!',
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'shop_url' => 'Server error',
            ]);
        }

        $data = $response->json();

        if (! isset($data['token'])) {
            throw ValidationException::withMessages([
                'shop_url' => 'Invalid authentication response',
            ]);
        }

        return $data['token'];
    }
}
