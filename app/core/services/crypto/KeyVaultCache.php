<?php declare(strict_types=1);

namespace app\core\services\crypto;

/**
 * Azure Key Vault secrets needed on most requests (config secrets - KeyVaultConfig, encryption key material -
 * SymmetricEncryption), cached so a request doesn't pay two HTTP calls (IMDS token + secret) each time (ADR-002).
 *
 * When APCu is enabled (web SAPI: Apache mod_php or php-fpm) a secret is cached in shared memory - never on disk -
 * and re-read from Key Vault every CACHE_FRESH_SECONDS, so a rotated value is picked up within that time. If Key
 * Vault is unreachable, the last fetched value keeps being used for up to CACHE_STALE_SECONDS since it was fetched,
 * so a Key Vault outage doesn't take the site down. Without APCu (CLI cron scripts: apc.enable_cli is off by
 * default) every call reads Key Vault.
 */
class KeyVaultCache
{
    private const CACHE_FRESH_SECONDS = 300;
    private const CACHE_STALE_SECONDS = 86400;
    private const CACHE_KEY_PREFIX = 'key_vault:';

    public static function getSecret(string $secret_name): string
    {
        if (!self::isCacheEnabled())
            return (new AzureKeyVaultClient())->getSecret($secret_name);

        $cache_key = self::CACHE_KEY_PREFIX . AZURE_KEY_VAULT_URL . '/' . $secret_name;
        $cached = apcu_fetch($cache_key);
        $has_cached = is_array($cached) && isset($cached['value'], $cached['fetched_at'], $cached['checked_at']);
        $now = time();
        if ($has_cached && $now - $cached['checked_at'] < self::CACHE_FRESH_SECONDS)
            return $cached['value'];

        try {
            $value = (new AzureKeyVaultClient())->getSecret($secret_name);
        } catch (\Exception $e) {
            $stale_left = $has_cached ? self::CACHE_STALE_SECONDS - ($now - $cached['fetched_at']) : 0;
            if ($stale_left <= 0)
                throw $e;
            error_log("Key Vault: refresh of secret $secret_name failed, using the cached value - " . $e->getMessage());
            // Retry after CACHE_FRESH_SECONDS, not on every request: each failed attempt can block for the client's timeout
            apcu_store($cache_key, ['value' => $cached['value'], 'fetched_at' => $cached['fetched_at'], 'checked_at' => $now], $stale_left);
            return $cached['value'];
        }
        apcu_store($cache_key, ['value' => $value, 'fetched_at' => $now, 'checked_at' => $now], self::CACHE_STALE_SECONDS);
        return $value;
    }

    private static function isCacheEnabled(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }
}
