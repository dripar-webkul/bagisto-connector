<?php

namespace Webkul\Bagisto\Tests\Unit\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Http\Controllers\CredentialController;
use Webkul\Bagisto\Http\Requests\CredentialCreateRequest;
use Webkul\Bagisto\Models\Credential;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Bagisto\Services\MappingSeeder;
use Webkul\Core\Repositories\ChannelRepository;

class CredentialControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_answers_with_a_message_when_the_store_cannot_be_reached()
    {
        $seeder = Mockery::mock(MappingSeeder::class);
        $seeder->shouldNotReceive('seed');

        $controller = new CredentialController(
            Mockery::mock(ChannelRepository::class),
            Mockery::mock(CredentialRepository::class),
            $seeder,
        );

        $request = Mockery::mock(CredentialCreateRequest::class);
        $request->shouldReceive('only')->andReturn([
            'shop_url' => 'http://127.0.0.1:9/unreachable',
            'email'    => 'admin@example.com',
            'password' => 'secret',
        ]);

        $response = $controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $response->getData(true));
    }

    public function test_it_saves_a_changed_shop_url()
    {
        $credential = Credential::create([
            'shop_url' => 'https://old-store.test',
            'email'    => 'admin@example.com',
            'password' => 'secret',
        ]);

        resolve(CredentialRepository::class)->update([
            'shop_url' => 'https://new-store.test',
        ], $credential->id);

        $this->assertSame('https://new-store.test', $credential->fresh()->shop_url);
    }

    public function test_it_invalidates_the_cached_api_client_for_the_credential()
    {
        $controller = new CredentialController(
            Mockery::mock(ChannelRepository::class),
            Mockery::mock(CredentialRepository::class),
            Mockery::mock(MappingSeeder::class),
        );

        foreach (CacheType::cases() as $cacheType) {
            Cache::put($cacheType->forCredential(42), 'stale', 60);
        }

        $forget = new \ReflectionMethod($controller, 'forgetCredentialCaches');
        $forget->setAccessible(true);
        $forget->invoke($controller, 42);

        $this->assertNull(Cache::get(CacheType::BAGISTO_API_HTTP->forCredential(42)));
        $this->assertNull(Cache::get(CacheType::CREDENTIAL->forCredential(42)));
    }
}
