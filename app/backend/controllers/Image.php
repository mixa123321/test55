<?php

namespace app\backend\controllers;

use app\core\Storage;
use app\core\helpers\FileWrapper;

class Image extends AuthController
{
    public function public()
    {
        $path = $_GET['path'] ?? '';
        if (!$path)
            die('Wrong path');
        $filename = Storage::getFile(SKUS_STORAGE, $path);
        $expires = (new \DateTime())->modify("+1 day")->format("D, d M Y H:i:s");
        $image = '';
        $image_mime_type = '';
        if (!empty($filename)) {
            $image = (string)stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($filename)))->getStream($filename));
            // type from the content itself (not from stored blob metadata or the file name)
            $image_mime_type = (string)(new \finfo(FILEINFO_MIME_TYPE))->buffer($image);
        }
        // serve raster images only: an SVG/HTML file in storage opened via this URL would run script on our origin
        if ($image === '' || strpos($image_mime_type, 'image/') !== 0 || stripos($image_mime_type, 'svg') !== false) {
            header('Content-Type: image/jpeg');
            header('Content-Length: 0');
            header('Cache-control: max-age=86400');
            header('Pragma: cache');
            header("expires: $expires GMT");
            echo '';
            exit();
        }

        header('Content-Type: ' . $image_mime_type);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Length: ' . strlen($image));
        header('Cache-control: max-age=86400');
        header('Pragma: cache');
        header("expires: $expires GMT");
        echo $image;
        exit();
    }
}