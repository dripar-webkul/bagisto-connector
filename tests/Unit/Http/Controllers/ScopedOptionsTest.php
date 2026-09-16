<?php

namespace Webkul\Bagisto\Tests\Unit\Http\Controllers;

use Illuminate\Http\Request;
use Tests\TestCase;
use Webkul\Bagisto\Http\Controllers\OptionController;
use Webkul\Bagisto\Models\Credential;
use Webkul\Core\Models\Channel;
use Webkul\Core\Models\Locale;

class ScopedOptionsTest extends TestCase
{
    private OptionController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = resolve(OptionController::class);
    }

    private function credentialMapping(array $storeInfo): Credential
    {
        return Credential::create([
            'shop_url'   => 'https://store-'.uniqid().'.test',
            'email'      => 'admin@example.com',
            'password'   => 'secret',
            'store_info' => array_map('json_encode', $storeInfo),
        ]);
    }

    private function optionsFor(string $method, array $query): array
    {
        $this->app->instance('request', Request::create('/', 'GET', $query));

        return $this->controller->{$method}()->getData(true)['options'];
    }

    private function anyChannel(): Channel
    {
        return Channel::query()->firstOrFail();
    }

    private function anyLocale(): Locale
    {
        return Locale::query()->where('status', 1)->firstOrFail();
    }

    public function test_it_offers_no_channel_until_a_credential_is_chosen()
    {
        $this->assertSame([], $this->optionsFor('listChannel', []));
    }

    public function test_it_offers_no_locale_until_a_credential_is_chosen()
    {
        $this->assertSame([], $this->optionsFor('listLocale', []));
    }

    public function test_it_offers_only_the_channels_the_credential_maps()
    {
        $channel = $this->anyChannel();

        $credential = $this->credentialMapping([[
            'channel' => ['default' => $channel->code],
            'locales' => ['en' => $this->anyLocale()->code],
        ]]);

        $options = $this->optionsFor('listChannel', ['credentials' => [$credential->id]]);

        $this->assertSame([$channel->code], array_column($options, 'code'));
    }

    public function test_it_offers_only_the_locales_the_credential_maps()
    {
        $locale = $this->anyLocale();

        $credential = $this->credentialMapping([[
            'channel' => ['default' => $this->anyChannel()->code],
            'locales' => ['en' => $locale->code],
        ]]);

        $options = $this->optionsFor('listLocale', ['credentials' => [$credential->id]]);

        $this->assertSame([$locale->code], array_column($options, 'code'));
    }

    public function test_it_offers_nothing_when_the_store_configuration_is_empty()
    {
        $credential = $this->credentialMapping([]);

        $this->assertSame([], $this->optionsFor('listChannel', ['credentials' => [$credential->id]]));
        $this->assertSame([], $this->optionsFor('listLocale', ['credentials' => [$credential->id]]));
    }

    public function test_options_carry_the_label_core_renders()
    {
        $channel = $this->anyChannel();

        $credential = $this->credentialMapping([[
            'channel' => ['default' => $channel->code],
            'locales' => ['en' => $this->anyLocale()->code],
        ]]);

        $options = $this->optionsFor('listChannel', ['credentials' => [$credential->id]]);

        $this->assertArrayHasKey('label', $options[0]);
        $this->assertNotSame('', $options[0]['label']);
    }
}
