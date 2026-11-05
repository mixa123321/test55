<?php

namespace app\core\handlers;

use app\core\entities\FileResponse;
use app\core\helpers\File;

class FileLoader
{
    public static function collectFile($url, $redirect = false): FileResponse
    {
        $file_response = new FileResponse();
        // the URL comes from API/backend users: public http(s) addresses only, pinned to the checked IP
        $resolve = File::publicUrlResolve((string)$url);
        if ($resolve === null) {
            $file_response->setResult(false);
            $file_response->setMessage('Failed url');
            return $file_response;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_RESOLVE, [$resolve]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_AUTOREFERER, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $file_response->setContent(curl_exec($ch));
        $info = curl_getinfo($ch);
        // the name comes from a third-party URL: keep only a safe base name and always store it as .pdf
        $url_name = basename((string)parse_url((string)$info['url'], PHP_URL_PATH));
        $file_response->setName($url_name === '' ? '' : File::safeFileName(rawurldecode($url_name), 'document', 'pdf'));
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $file_response->setResult(false);
        if (empty($file_response->getContent()) || $code === 404) {
            $file_response->setMessage('Failed url');
            return $file_response;
        }
        if ($info['content_type'] !== 'application/pdf') {
            if (!empty($info['redirect_url']) && !$redirect)
                return self::collectFile($info['redirect_url'], true);
            elseif (is_null($info['content_type'])) {
                $file_response->setMessage('Can`t get file info');
                return $file_response;
            } else {
                $file_response->setMessage('Must be PDF file');
                return $file_response;
            }
        }
        // Content-Type is whatever the remote server claims; the content itself must be a PDF
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer((string)$file_response->getContent()) !== 'application/pdf') {
            $file_response->setMessage('Must be PDF file');
            return $file_response;
        }
        $size = $info['size_download'] ?? null;
        if ($size > File::MAX_FILE_PDF_SIZE || is_null($size)) {
            $file_response->setMessage('File size is ' . File::formatMB((float)$size) . ' MB. Maximum file size ' . File::formatMB(File::MAX_FILE_PDF_SIZE) . ' MB');
            return $file_response;
        }
        $file_response->setResult(true);
        return $file_response;
    }
}