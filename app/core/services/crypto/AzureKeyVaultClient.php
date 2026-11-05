<?php declare(strict_types=1);

namespace app\core\services\crypto;

use app\core\Environment;

/**
 * Fetches a secret from Azure Key Vault. Per ADR-002
 * (docs/project/architecture/adr-002-azure-key-vault-secrets.md): called
 * once per request, result held only in memory for that request's lifetime
 * (see $token_cache) — never written to Redis, disk, or the DB.
 *
 * Production runs on an Azure VM with Managed Identity attached (confirmed
 * with DevOps, 2026-09-23) — getAccessTokenFromManagedIdentity()'s use of
 * the Instance Metadata Service endpoint is the correct mechanism for this
 * target. DevOps still needs to actually attach the Managed Identity to the
 * VM and grant it `get`-secret access on the vault before this works end to
 * end.
 *
 * Local/CI uses `james-gould/azure-keyvault-emulator` (docker-compose
 * service `kv-emulator`, see docker/kv-emulator/) instead — spiked and
 * confirmed 2026-09-23 that getSecret()/request() work against it unmodified
 * over REST (its docs emphasize the official Azure SDK clients, but it's a
 * plain REST server underneath — same PUT/GET /secrets/{name}?api-version=
 * shape as real Key Vault). It does NOT emulate the Instance Metadata
 * Service though — it exposes its own local OAuth2 client_credentials token
 * endpoint instead (accepts any client_id/client_secret, see
 * getAccessTokenFromEmulator()), which is why getAccessToken() branches on
 * Environment::isDev().
 *
 * Used by SymmetricEncryption to fetch `symmetric-encryption-data` lazily,
 * on first encrypt/decrypt, in any environment whose config defines
 * AZURE_KEY_VAULT_URL. An environment without it never calls Key Vault, and
 * requests that don't encrypt anything don't either.
 */
class AzureKeyVaultClient
{
    private const IMDS_TOKEN_URL = 'http://169.254.169.254/metadata/identity/oauth2/token';
    private const IMDS_API_VERSION = '2018-02-01';
    private const KEY_VAULT_RESOURCE = 'https://vault.azure.net';
    private const SECRET_API_VERSION = '7.4';

    /** @var string|null process-lifetime only — one Key Vault call per request, never persisted */
    private static $token_cache;

    public function getSecret(string $secret_name): string
    {
        $token = $this->getAccessToken();
        $url = rtrim(AZURE_KEY_VAULT_URL, '/') . '/secrets/' . rawurlencode($secret_name) . '?api-version=' . self::SECRET_API_VERSION;

        $response = $this->request($url, $token);
        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['value'])) {
            throw new \Exception('Azure Key Vault: unexpected secret response');
        }

        return (string)$data['value'];
    }

    private function getAccessToken(): string
    {
        if (self::$token_cache !== null) {
            return self::$token_cache;
        }

        return self::$token_cache = Environment::isDev()
            ? $this->getAccessTokenFromEmulator()
            : $this->getAccessTokenFromManagedIdentity();
    }

    private function getAccessTokenFromManagedIdentity(): string
    {
        $url = self::IMDS_TOKEN_URL
            . '?api-version=' . self::IMDS_API_VERSION
            . '&resource=' . rawurlencode(self::KEY_VAULT_RESOURCE);

        $response = $this->request($url, null, ['Metadata: true']);
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new \Exception('Azure Key Vault: unable to obtain a Managed Identity access token');
        }

        return (string)$data['access_token'];
    }

    /**
     * Local/CI only. The emulator isn't a real authorization boundary — it
     * accepts any client_id/client_secret — so a fixed placeholder pair is
     * fine here; there is nothing to keep secret in a dev-only local emulator.
     */
    private function getAccessTokenFromEmulator(): string
    {
        if (!defined('AZURE_KEY_VAULT_EMULATOR_TOKEN_URL'))
            throw new \Exception('Azure Key Vault: AZURE_KEY_VAULT_EMULATOR_TOKEN_URL is not configured for this dev environment');

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => 'local-dev',
            'client_secret' => 'local-dev',
            'scope' => self::KEY_VAULT_RESOURCE . '/.default',
        ]);

        $response = $this->request(
            AZURE_KEY_VAULT_EMULATOR_TOKEN_URL,
            null,
            ['Content-Type: application/x-www-form-urlencoded'],
            $body
        );
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new \Exception('Azure Key Vault: unable to obtain a token from the local emulator');
        }

        return (string)$data['access_token'];
    }

    /**
     * Deliberately does not use app\core\helpers\CURL: that helper logs every
     * response body to the curl_logs DB table by default (CurlLogger::log(),
     * see docs/project/backlog.md BL-009). Using it here would write Key
     * Vault secret values and Managed Identity access tokens into an
     * admin-viewable, unredacted log table — defeating the entire point of
     * this class. Uses raw curl_* instead.
     */
    private function request(string $url, ?string $bearer_token, array $extra_headers = [], ?string $post_body = null): string
    {
        $headers = $extra_headers;
        if ($bearer_token !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer_token;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($post_body !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $post_body;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($response === false || $curl_error !== null || !is_string($response)) {
            throw new \Exception('Azure Key Vault: request failed - ' . ($curl_error ?? 'unknown error'));
        }
        if ($http_code < 200 || $http_code >= 300) {
            throw new \Exception('Azure Key Vault: unexpected HTTP status ' . $http_code . self::describeError($response));
        }

        return $response;
    }

    /**
     * Error bodies from Key Vault ({"error": {"code", "message"}}) and from the Instance Metadata Service
     * ({"error", "error_description"}) never carry secret values, so their code and message are safe to
     * surface; anything else in the body is ignored.
     */
    private static function describeError(string $response): string
    {
        $data = json_decode($response, true);
        if (!is_array($data))
            return '';
        $error = $data['error'] ?? null;
        if (is_array($error))
            return ' - ' . ($error['code'] ?? '') . ': ' . ($error['message'] ?? '');
        if (is_string($error))
            return ' - ' . $error . ': ' . ($data['error_description'] ?? '');
        return '';
    }
}
