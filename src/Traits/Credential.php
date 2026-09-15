<?php

namespace Webkul\Bagisto\Traits;

use Illuminate\Support\Facades\Cache;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Support\CredentialScope;

trait Credential
{
    use EncryptableTrait;

    protected function initializeCredential($filters): void
    {
        $cacheKey = CacheType::CREDENTIAL->forCredential($filters['credentials'] ?? null);

        $this->credential = Cache::get($cacheKey, []);

        if (empty($this->credential)) {
            $activeCredential = $this->credentialRepository->find($filters['credentials']);
            if ($activeCredential) {
                $this->credential = [
                    'id'              => $activeCredential->id,
                    'shop_url'        => $activeCredential->shop_url,
                    'email'           => $activeCredential->email,
                    'password'        => $this->decryptValue($activeCredential->password),
                    'store_info'      => $activeCredential->store_info,
                    'additional_info' => $activeCredential->additional_info,
                ];
            }

            Cache::put($cacheKey, $this->credential, config('session.lifetime'));
        }
    }

    protected function getCredential(): array
    {
        return $this->credential;
    }

    protected function getMappedLocales(): array
    {
        return CredentialScope::localeMap($this->credential['store_info'] ?? []);
    }

    protected function decodeStoreInfo(): array
    {
        return CredentialScope::decode($this->credential['store_info'] ?? []);
    }

    protected function findMappedChannel(string $channel): ?string
    {
        $mappedChannel = null;

        $allChannels = $this->getMappedChannels();

        foreach ($allChannels as $key => $value) {
            if ($value === $channel) {
                $mappedChannel = $key;
                break;
            }
        }

        return $mappedChannel;
    }

    protected function getMappedChannels(): array
    {
        return CredentialScope::channelMap($this->credential['store_info'] ?? []);
    }
}
