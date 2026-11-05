<?php

namespace app\core\helpers;

class SftpGoRest
{
    //TODO обсудить
    const PREFIX = '/upload/';
    private $host;
    private $token;
    private $api_key = SFTP_CLIENT_X_SFTPGO_API_KEY;

    public function __construct()
    {
        $this->host = SFTP_CLIENT_HOST;
    }

    public function createFolder(string $path, bool $mkdir_parents = true): bool
    {
        if (empty($this->token))
            throw new \Exception('Empty token SFTP GO');
        $mkdir_parents = $mkdir_parents ? "true" : "false";
        $curl = new CURL($this->host . "/api/v2/user/dirs?path=$path&mkdir_parents=$mkdir_parents", 'sftp_go');
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Authorization', "Bearer " . $this->token);
        $curl->exec();
        return in_array($curl->getResultCode(), [201]);
    }

    public function health(): bool
    {
        $curl = new CURL($this->host . '/healthz', 'sftp_go');
        $result = $curl->exec();
        if ($curl->getResultCode() != 200)
            return false;
        if ($result !== 'ok')
            return false;
        return true;
    }

    public function setToken(string $username, string $password): bool
    {
        $curl = new CURL($this->host . '/api/v2/user/token', 'sftp_go');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Authorization', "Basic " . base64_encode("$username:$password"));
        $result = $curl->exec();
        if ($curl->getResultCode() != 200)
            return false;
        $result = json_decode($result, true);
        if (empty($result['access_token']))
            return false;
        $this->token = $result['access_token'];
        return true;
    }

    public function getUser(string $name)
    {
        $curl = new CURL($this->host . "/api/v2/users/$name?confidential_data=1", 'sftp_go');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('X-SFTPGO-API-KEY', $this->api_key);
        return $curl->exec();
    }

    public function createUser(string $name, string $password): bool
    {
        $body = [
            'id' => 1,
            'status' => 1,
            'username' => $name,
            'password' => $password,
            'has_password' => true,
            'home_dir' => '/srv/sftpgo/data/' . $name,
            'uid' => 0,
            'gid' => 0,
            'max_sessions' => 0,
            'quota_size' => 0,
            'quota_files' => 0,
            'permissions' => [
                    '/' => [
                            0 => 'list',
                            1 => 'download',
                        ],
                    '/upload' =>
                        [
                            0 => 'list',
                            1 => 'download',
                            2 => 'upload',
                            3 => 'overwrite',
                            4 => 'create_dirs',
                            5 => 'rename_files',
                            6 => 'rename_dirs',
                            7 => 'delete_files',
                            8 => 'delete_dirs',
                        ],
                ],
            'used_quota_size' => 0,
            'used_quota_files' => 0,
            'last_quota_update' => 0,
            'upload_bandwidth' => 0,
            'download_bandwidth' => 0,
            'upload_data_transfer' => 0,
            'download_data_transfer' => 0,
            'total_data_transfer' => 0,
            'used_upload_data_transfer' => 0,
            'used_download_data_transfer' => 0,
            'created_at' => 0,
            'updated_at' => 0,
            'last_login' => 0,
            'first_download' => 0,
            'first_upload' => 0,
            'last_password_change' => 0,
            'filters' => [
                'hooks' => [
                    'external_auth_disabled' => false,
                    'pre_login_disabled' => false,
                    'check_password_disabled' => false,
                ],
                'totp_config' => [
                    'secret' => new \stdClass(),
                ],
            ],
            'filesystem' => [
                'provider' => 0,
                'osconfig' => new \stdClass(),
                's3config' => [
                    'access_secret' => new \stdClass(),
                ],
                'gcsconfig' => [
                    'credentials' => new \stdClass(),
                ],
                'azblobconfig' => [
                    'account_key' => new \stdClass(),
                    'sas_url' => new \stdClass(),
                ],
                'cryptconfig' => [
                    'passphrase' => new \stdClass(),
                ],
                'sftpconfig' => [
                    'password' => new \stdClass(),
                    'private_key' => new \stdClass(),
                    'key_passphrase' => new \stdClass(),
                ],
                'httpconfig' => [
                    'password' => new \stdClass(),
                    'api_key' => new \stdClass(),
                ],
            ],
            'groups' => [
                [
                    'name' => 'Azure default blob storage',
                    'type' => 1,
                ],
            ],
        ];

        $curl = new CURL($this->host . "/api/v2/users", 'sftp_go');
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('X-SFTPGO-API-KEY', $this->api_key);
        $curl->setBody(json_encode($body));
        $curl->exec();

        return $curl->getResultCode() == 201;
    }

    public static function handleDir(string $login, string $password, string $path): bool
    {
        $sftp_go_rest = new self();
        if (!$sftp_go_rest->setToken($login, $password))
            return false;
        $path = (new FileWrapper(FileWrapper::getContainerByPath($path)))->filterPath($path);
        return $sftp_go_rest->createFolder($path);
    }
}