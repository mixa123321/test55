<?php declare(strict_types=1);

namespace app\core\services\crypto;

/**
 * Thin static facade over SymmetricEncryption, so call sites read as
 * `Crypto::decrypt($ciphertext)` instead of instantiating
 * SymmetricEncryption directly. Per ADR-002
 * (docs/project/architecture/adr-002-azure-key-vault-secrets.md): the key
 * SymmetricEncryption uses is fetched lazily from Azure Key Vault (the local
 * emulator in dev) in any environment that defines AZURE_KEY_VAULT_URL.
 */
class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        return (new SymmetricEncryption())->encrypt($plaintext);
    }

    public static function decrypt(string $ciphertext): string
    {
        return (new SymmetricEncryption())->decrypt($ciphertext);
    }
}
