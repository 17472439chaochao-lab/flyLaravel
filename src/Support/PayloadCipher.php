<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

use RuntimeException;

final class PayloadCipher
{
    private const NONCE_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public static function enabled(): bool
    {
        return (bool) config('toolkit.payload_encryption.enabled', false) && self::configuredKey() !== '';
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LENGTH);

        if ($cipher === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Payload 加密失败');
        }

        return base64_encode($nonce.$tag.$cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) <= self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Payload 密文格式错误');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $cipher = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plain === false) {
            throw new RuntimeException('Payload 解密失败');
        }

        return $plain;
    }

    private static function key(): string
    {
        return hash('sha256', self::configuredKey(), true);
    }

    private static function configuredKey(): string
    {
        $key = (string) config('toolkit.payload_encryption.key', '');

        if ($key === '') {
            $key = (string) config('project.aes.encrypt_key', '');
        }

        return $key;
    }
}
