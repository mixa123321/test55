<?php declare(strict_types=1);

namespace app\core\services\crypto;

class SymmetricEncryption
{
    private const KEY_VAULT_SECRET_NAME = 'symmetric-encryption-data';

    /** @var string|null process-lifetime copy; across requests it is cached by KeyVaultCache (APCu, never disk) */
    private static $encrypt_data_json;

    protected $cipher;
    protected $iv_len; // Длинна зависит от типа шифрования
    protected $tag_len; // Длинна зависит от типа шифрования
    protected $key_prev; // старый ключ, нужно чтобы перешифровать пароли
    protected $key_current; // текущий ключ
    protected $options;
    protected $pack_to_text = true;

    public function __construct(bool $pack_to_text = true)
    {
        $data = $this->getEncryptData();
        $this->key_prev = $data['key_prev'];
        $this->key_current = $data['key_current'];
        $this->cipher = $data['cipher'];
        $this->iv_len = $data['iv_len'];
        $this->tag_len = $data['tag_len'];
        $this->options = $data['options'] ?? 0;

        $this->pack_to_text = $pack_to_text;
    }

    public function encrypt(string $plaintext, bool $prev = false): string
    {
        if (!$this->isValidCipher($this->cipher)) {
            throw new \Exception("Invalid cipher");
        }
        $iv = $this->getRandomPseudoBytes();
        if (!$this->isValidIvLen($iv)) {
            throw new \Exception("Invalid IV length");
        }
        $ciphertext = openssl_encrypt($plaintext, $this->cipher, $prev ? $this->key_prev : $this->key_current, $this->options, $iv, $tag);
        if ($ciphertext === false) {
            throw new \Exception("Unable to encrypt data");
        }
        if (!$this->isValidTagLen($tag)) {
            throw new \Exception("Invalid tag length");
        }
        $pack = $this->pack($iv, $tag, $ciphertext);
        return $this->pack_to_text ? $this->packToText($pack) : $pack;
    }

    public function decrypt(string $text, bool $prev = false): string
    {
        $text = $this->pack_to_text ? $this->unPackToText($text) : $text;
        if ($text === false) {
            throw new \Exception("Unable to decrypt data");
        }
        $iv = $this->getIvFromPack($text);
        $tag = $this->getTagFromPack($text);
        $ciphertext = $this->getCipherTextFromPack($text);
        $text = openssl_decrypt($ciphertext, $this->cipher, $prev ? $this->key_prev : $this->key_current, $this->options, $iv, $tag);
        if ($text === false) {
            throw new \Exception("Unable to decrypt data");
        }
        return $text;
    }

    public function getNewEncryptFromOldEncrypt(string $text_prev)
    {
        $text = $this->decrypt($text_prev, true);
        return $this->encrypt($text);
    }

    protected function isValidCipher(string $cipher): bool
    {
        return in_array($cipher, $this->getsAvailableCipherMethods());
    }

    protected function isValidIvLen(string $iv): bool
    {
        return strlen($iv) === $this->iv_len;
    }

    protected function isValidTagLen(string $tag): bool
    {
        return strlen($tag) === $this->tag_len;
    }

    protected function pack(string $iv, string $tag, string $ciphertext): string
    {
        return $iv . $tag . $ciphertext;
    }

    protected function getIvFromPack(string $pack): string
    {
        $iv = substr($pack, 0, $this->iv_len);
        if (!$this->isValidIvLen($iv)) {
            throw new \Exception("Invalid IV length");
        }
        return $iv;
    }

    protected function getTagFromPack(string $pack): string
    {
        $tag = substr($pack, $this->iv_len, $this->tag_len);
        if (!$this->isValidTagLen($tag)) {
            throw new \Exception("Invalid tag length");
        }
        return $tag;
    }

    protected function getCipherTextFromPack(string $pack): string
    {
        if (strlen($pack) < $this->iv_len + $this->tag_len) {
            throw new \Exception("Invalid ciphertext length");
        }
        return substr($pack, $this->iv_len + $this->tag_len);
    }

    protected function getsAvailableCipherMethods()
    {
        return openssl_get_cipher_methods();
    }

    protected function getRandomPseudoBytes(): string
    {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        return openssl_random_pseudo_bytes($ivlen);
    }

    /**
     * Key material comes from SYMETRIC_ENCRYPTION_DATA when an environment defines it explicitly (unit tests),
     * otherwise from the `symmetric-encryption-data` Azure Key Vault secret of any environment that sets
     * AZURE_KEY_VAULT_URL (ADR-002). Fetched lazily on first use, so requests that never encrypt don't touch it,
     * and read through KeyVaultCache, so requests that do don't each pay the Key Vault round trips.
     */
    protected function getEncryptData(): array
    {
        $data = json_decode(self::getEncryptDataJson(), true);
        if (!is_array($data)) {
            throw new \Exception('Symmetric encryption data is empty');
        }
        if (empty($data['key_prev']) || empty($data['key_current']) || empty($data['cipher']) || empty($data['iv_len']) || empty($data['tag_len']) || !isset($data['options'])) {
            throw new \Exception('Symmetric encryption data is wrong');
        }
        return $data;
    }

    private static function getEncryptDataJson(): string
    {
        if (self::$encrypt_data_json !== null)
            return self::$encrypt_data_json;
        if (defined('SYMETRIC_ENCRYPTION_DATA'))
            return self::$encrypt_data_json = SYMETRIC_ENCRYPTION_DATA;
        if (!defined('AZURE_KEY_VAULT_URL') || AZURE_KEY_VAULT_URL === '')
            throw new \Exception('Symmetric encryption data is not configured: neither SYMETRIC_ENCRYPTION_DATA nor AZURE_KEY_VAULT_URL is defined');

        return self::$encrypt_data_json = KeyVaultCache::getSecret(self::KEY_VAULT_SECRET_NAME);
    }

    protected function packToText(string $pack): string
    {
        return base64_encode($pack);
    }

    protected function unPackToText(string $text)
    {
        return base64_decode($text, true);
    }
}
