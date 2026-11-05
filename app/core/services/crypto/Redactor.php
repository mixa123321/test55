<?php declare(strict_types=1);

namespace app\core\services\crypto;

/**
 * Masks secret values before data is written to history and log tables (ADR-002 Phase 2 step 4, BL-009): the secrets
 * themselves are encrypted at rest, copies of them in channel/supplier history and curl_logs must not stay readable.
 *
 * A value is secret when its key is one of SECRET_KEYS (case-insensitive, at any depth). The key is kept, so history
 * still shows that the value was set or changed; empty values stay empty, so it also shows that one was cleared.
 */
class Redactor
{
    public const MASK = '***';

    public const SECRET_KEYS = [
        // shop channels (Shopify, WooCommerce, Magento)
        'api_key', 'api_secret', 'access_token', 'refresh_token', 'client_secret', 'consumer_key', 'consumer_secret', 'token',
        // flat/SFTP, Visible and SFTPGo logins, Retain.me
        'pass', 'password', 'pwd', 'orders_pwd', 'print_pwd',
        // suppliers_warehouses columns (supplier history)
        'shipping_api_key', 'retainme_credentials', 'virtual_credentials',
    ];

    /** @param int|string $key */
    public static function isSecretKey($key): bool
    {
        return is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true);
    }

    /**
     * Returns $data with the value of every secret key masked, at any depth.
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value)
            if (self::isSecretKey($key))
                $data[$key] = self::mask($value);
            elseif (is_array($value))
                $data[$key] = self::redact($value);
        return $data;
    }

    /**
     * Masks every non-empty scalar of $value - the whole value of a secret key, e.g. an old/new pair of a diff.
     * @param mixed $value
     * @return mixed
     */
    public static function mask($value)
    {
        if (is_array($value))
            return array_map([self::class, 'mask'], $value);
        return $value === null || $value === '' || is_bool($value) ? $value : self::MASK;
    }

    /**
     * redact() for a request/response body or a query string kept as text, JSON or form-encoded. Text that holds no
     * secret key or is in neither format is returned unchanged.
     */
    public static function redactText(?string $text): ?string
    {
        if ($text === null || $text === '' || !self::mentionsSecretKey($text))
            return $text;
        $json = json_decode($text, true);
        if (is_array($json)) {
            $redacted = self::redact($json);
            return $redacted === $json ? $text : (string)json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (strpos($text, '=') !== false && !preg_match('/\s/', $text)) {
            parse_str($text, $form);
            $redacted = self::redact($form);
            return $redacted === $form ? $text : http_build_query($redacted);
        }
        return $text;
    }

    /** Cheap check before decoding: every secret key contains one of these words. */
    private static function mentionsSecretKey(string $text): bool
    {
        return (bool)preg_match('/key|secret|token|pass|pwd|credentials/i', $text);
    }
}
