<?php declare(strict_types=1);

namespace app\core\services\crypto;

/**
 * Defines the environment's secret config constants from one JSON Azure Key Vault secret instead of plaintext
 * values in app/config/env/*.php (ADR-002, ABF-474).
 *
 * Opt-in per environment: the env config defines AZURE_KEY_VAULT_URL and AZURE_KEY_VAULT_CONFIG_SECRET (the
 * secret's name, e.g. 'app-config'). The secret holds a flat JSON object of constant name => scalar value.
 * A constant present both in the env file and in the secret is a configuration error and fails loudly, so a
 * rotated value in Key Vault can never be silently shadowed by a stale one left in the file.
 *
 * The secret is needed on every request (DB credentials), so it is read through KeyVaultCache (APCu; refresh
 * interval and outage fallback are defined there).
 */
class KeyVaultConfig
{
    public static function load(): void
    {
        if (!defined('AZURE_KEY_VAULT_URL') || !defined('AZURE_KEY_VAULT_CONFIG_SECRET'))
            return;
        foreach (self::parse(KeyVaultCache::getSecret((string)AZURE_KEY_VAULT_CONFIG_SECRET)) as $name => $value) {
            if (defined($name))
                throw new \Exception("Key Vault config: constant $name is also defined in the env config file - remove it from the file");
            define($name, $value);
        }
    }

    /** @return array<string, scalar> */
    public static function parse(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !$data)
            throw new \Exception('Key Vault config: secret is not a non-empty JSON object');

        $constants = [];
        foreach ($data as $name => $value) {
            if (!is_string($name) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $name))
                throw new \Exception('Key Vault config: invalid constant name ' . json_encode($name));
            if (!is_scalar($value))
                throw new \Exception("Key Vault config: value of $name must be a string, number or boolean");
            $constants[$name] = $value;
        }
        return $constants;
    }
}
