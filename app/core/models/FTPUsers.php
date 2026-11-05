<?php

namespace app\core\models;

use app\core\dbal\MysqlConnectionFactory;
use app\core\dbal\SafeMysql;
use app\core\entities\FTPUser;
use app\core\helpers\File;
use app\core\helpers\FileWrapper;
use app\core\helpers\SftpGoRest;
use app\core\helpers\Debug;

class FTPUsers
{
    const DIR_UPLOAD = 'upload';
    const DIR_REPORT = 'report';
    const DIR_BILLING = 'billing';

    public static function addUser(string $login, string $password, string $path)
    {
        $data = [
            'userid' => $login,
            'passwd' => $password,
            'homedir' => File::clearPath($path),
            'accessed' => time(),
            'modified' => time(),
        ];
        $ftp_user = new FTPUser($data);
        $mysql = new SafeMysql(MysqlConnectionFactory::DB_SFTP);
        $mysql->beginTransaction();
        $ftp_user->save(array_keys($data));
        $mysql->commit();
    }

    public static function updateUser(string $login, string $password)
    {
        $password = FTPUser::getPassword($password);
        (new SafeMysql())->execute("UPDATE ftp_users SET passwd = :passwd WHERE userid = :userid", [
            'passwd' => $password,
            'userid' => $login,
        ]);
    }

    public static function createUser(string $login, string $password): bool
    {
        $sftp_go_rest = new SftpGoRest();
        if ($sftp_go_rest->createUser($login, $password)) {
            return true;
        }
        Debug::sendDebugInfo("Not create SFTPGo user", 'User ' . $login);
        return false;
    }

    public static function initSftpFolders(int $supplier_id)
    {
        $sftp = SFTPExports::getBySupplierId($supplier_id);
        if (!$sftp)
            return;
        $clear_login = FTPUsers::clearLogin($sftp->getLogin(), $supplier_id);
        $path = SFTP_STORAGE;
        $folders = [];
        foreach (SFTPUploads::getBySFTPExport($sftp->getId()) as $item) {
            if (!$item['is_active'])
                continue;
            $folders[] = $path . DIRECTORY_SEPARATOR . self::DIR_UPLOAD . DIRECTORY_SEPARATOR . $item['title'];
            if ($item['title'] === 'Orders')
                $folders[] = $path . DIRECTORY_SEPARATOR . self::DIR_UPLOAD . DIRECTORY_SEPARATOR . 'UploadDocuments';
        }
        foreach ($folders as $folder)
            SftpGoRest::handleDir($clear_login, $sftp->getPassword(), $folder);
    }

    public static function clearLogin(string $login, int $supplier_id): string
    {
        return $login;
        //return preg_replace('/_' . $supplier_id . '$/', '', $login);
    }
}
