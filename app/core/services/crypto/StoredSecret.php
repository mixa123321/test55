<?php declare(strict_types=1);

namespace app\core\services\crypto;

/**
 * Encryption at rest for secrets the application stores in the DB (ADR-002 Phase 2, BL-008): API tokens, client
 * passwords and the like that have to be sent to an external system, so they can't be hashed.
 *
 * Encrypted values are SymmetricEncryption output with a PREFIX, so encrypted and not yet migrated plaintext values
 * can live side by side: decrypt() returns a value without the prefix unchanged. This keeps reads working during the
 * rollout and in environments that never opt in.
 *
 * Writes encrypt only when the environment opts in with define('ENCRYPT_STORED_SECRETS', true) - after its key
 * material is confirmed readable (`php robot/tasks/secure/check_key_vault.php symmetric-encryption-data`). Without
 * it values are stored as before, so deploying this code changes nothing until an environment is ready.
 */
class StoredSecret
{
    public const PREFIX = 'enc:v1:';

    public static function isEnabled(): bool
    {
        return defined('ENCRYPT_STORED_SECRETS') && ENCRYPT_STORED_SECRETS;
    }

    public static function isEncrypted(string $value): bool
    {
        return strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    /**
     * Returns the value to store: encrypted when the environment opted in, unchanged otherwise.
     * Empty and already encrypted values are returned unchanged, so calling it twice is harmless.
     */
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || self::isEncrypted($value) || !self::isEnabled())
            return $value;
        return self::PREFIX . (new SymmetricEncryption())->encrypt($value);
    }

    /**
     * Returns the plaintext of a stored value; a value without PREFIX (not migrated yet) is returned unchanged.
     */
    public static function decrypt(?string $value): ?string
    {
        if ($value === null || !self::isEncrypted($value))
            return $value;
        $ciphertext = substr($value, strlen(self::PREFIX));
        $cipher = new SymmetricEncryption();
        try {
            return $cipher->decrypt($ciphertext);
        } catch (\Exception $e) {
            // Encrypted before the last key rotation and not re-encrypted yet: key_prev still opens it
            return $cipher->decrypt($ciphertext, true);
        }
    }

    /**
     * Re-encrypts a stored value with the current key after a key rotation: returns the new value, or null when there
     * is nothing to do (plaintext, or already encrypted with the current key). Throws when neither the current nor the
     * previous key opens it.
     */
    public static function reencrypt(?string $value): ?string
    {
        if ($value === null || !self::isEncrypted($value))
            return null;
        $ciphertext = substr($value, strlen(self::PREFIX));
        $cipher = new SymmetricEncryption();
        try {
            $cipher->decrypt($ciphertext);
            return null;
        } catch (\Exception $e) {
            // Not the current key: getNewEncryptFromOldEncrypt() opens it with key_prev or throws
        }
        return self::PREFIX . $cipher->getNewEncryptFromOldEncrypt($ciphertext);
    }

    /**
     * encrypt() for the given keys of a row or a JSON-decoded array; keys that are missing or hold no string are left
     * as they are.
     * @param string[] $fields
     */
    public static function encryptFields(array $data, array $fields): array
    {
        foreach ($fields as $field)
            if (isset($data[$field]) && is_string($data[$field]))
                $data[$field] = self::encrypt($data[$field]);
        return $data;
    }

    /**
     * decrypt() for the given keys of a row or a JSON-decoded array; keys that are missing or hold no string are left
     * as they are.
     * @param string[] $fields
     */
    public static function decryptFields(array $data, array $fields): array
    {
        foreach ($fields as $field)
            if (isset($data[$field]) && is_string($data[$field]))
                $data[$field] = self::decrypt($data[$field]);
        return $data;
    }
}
