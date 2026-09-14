<?php

namespace Webkul\Bagisto\Tests\Unit\Http\Controllers;

use Mockery;
use Tests\TestCase;
use Webkul\Bagisto\Http\Controllers\CredentialController;
use Webkul\Bagisto\Http\Requests\CredentialCreateRequest;
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
}
