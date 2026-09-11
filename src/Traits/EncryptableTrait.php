<?php

namespace Webkul\Bagisto\Traits;

trait EncryptableTrait
{
    private const ENCRYPTION_METHOD = 'AES-128-CBC';

    private function encryptValue(string $value): string
    {
        $key = config('app.key');
        $iv = random_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($value, self::ENCRYPTION_METHOD, $key, 0, $iv);

        return base64_encode($iv.$encrypted);
    }

    private function decryptValue(string $encryptedValue): string
    {
        $key = config('app.key');
        $data = base64_decode($encryptedValue);
        $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        return openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);
    }
}
