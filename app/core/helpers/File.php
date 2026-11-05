<?php

namespace app\core\helpers;

use app\core\Environment;
use app\core\ErrorHandler;
use app\core\models\Messages;

define('OUTPUT_ATTACHMENT', 'attachment');
define('OUTPUT_INLINE', 'inline');

class File
{
    private static $MIME_TYPE_IMAGES = [
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
    ];

    private static $MIME_TYPE_CSV = [
        'text/csv',
        'text/plain',
        'application/csv',
        'text/comma-separated-values',
        'application/excel',
        'application/vnd.ms-excel',
        'application/vnd.msexcel',
        'text/anytext',
        'application/octet-stream',
        'application/txt'
    ];

    private const EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'pgif', 'shtml',
        'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd',
    ];

    private const ZPL_WIDTH = 1600;
    private const ZPL_PRINTWIDTH = 1180;

    const SMALL_PRINTER = '8dpmm';
    const BIG_PRINTER = '12dpmm';

    const SMALL_PRINTER_DPI = '203'; //DPI = DPMM * 25.4
    const BIG_PRINTER_DPI = '300'; //DPI = DPMM * 25.4

    const DPMM_DPI_MAP = [
        self::SMALL_PRINTER => self::SMALL_PRINTER_DPI,
        self::BIG_PRINTER => self::BIG_PRINTER_DPI
    ];

    public const MAX_FILE_PDF_SIZE = 10 * (1 << 20);

    private const DOWNLOAD_MAX_REDIRECTS = 5;

    public static function clearPath($path)
    {
        $subdirs = explode($separator = '\\', $path);
        if (count($subdirs) == 1)
            $subdirs = explode($separator = '/', $subdirs[0]);
        $pred_path = [];
        foreach ($subdirs as $subdir)
            if (empty($subdir) || strpos($subdir, ':')) {
                $pred_path[] = $subdir;
                array_shift($subdirs);
            }
            else
                break;
        $path = [];
        foreach ($subdirs as $subdir)
            if ($subdir == '..')
                if (empty($path))
                    $pred_path[] = $subdir;
                else
                    array_pop($path);
            else
                $path[] = $subdir;
        return (!empty($pred_path) ? (implode($separator, $pred_path) . $separator) : '') . implode($separator, $path);
    }

    public static function fileNameEscape($file_name)
    {
        return str_replace(DIRECTORY_SEPARATOR, '', $file_name);
    }

    public static function outputFile($file_path, $disposition = OUTPUT_ATTACHMENT, $content_type = '')
    {
        $container = FileWrapper::getContainerByPath($file_path);
        $file_wrapper = new FileWrapper($container);
        if (!$file_wrapper->isFile($file_path))
            return false;
        $size = $file_wrapper->getFileSizeBlob($file_path);
        if (!$size)
            return false;
        $content_type = $content_type ?: $file_wrapper->getMimeType($file_path) ?: 'text/plain';
        $filename = basename($file_path);
        header("Content-Type: " . $content_type);
        header("Content-Disposition: $disposition; filename=\"{$filename}\"");
        header('Content-Transfer-Encoding: binary');
        if (stripos($file_path, "zip") !== 0) {
            header('Content-Length: ' . $size);
        }
        $stream = $file_wrapper->getStream($file_path);
        echo stream_get_contents($stream);
        fclose($stream);
        exit;
    }

    public static function isFileLocked($filepath, $is_unset = 0)
    {
        static $fp = array();
        if ($is_unset) {
            if (empty($fp[$filepath]))
                return false;
            flock($fp[$filepath], LOCK_UN);
            fclose($fp[$filepath]);
            return true;
        }
        $lock_file_exist = is_file($filepath);
        $fp[$filepath] = fopen($filepath, "w");
        if (!$lock_file_exist)
            chmod($filepath, 0777);
        if (!flock($fp[$filepath], LOCK_EX | LOCK_NB, $wouldblock)) {
            //Another process locked a file
            if ($wouldblock)
                return true;
            //Cannot lock a file, reason unknown.
            if (Environment::isProduction())
                Debug::sendDebugInfo("cannot lock a file", $filepath);
            return true;
        }
        return false;
    }

    /**
     * SSRF guard for outbound requests to user-supplied URLs: http(s) only, no credentials in the URL, and every
     * address the host resolves to must be public (no private/loopback/link-local/reserved ranges, e.g. cloud
     * metadata 169.254.169.254). Returns the CURLOPT_RESOLVE entry that pins the checked address, or null.
     */
    public static function publicUrlResolve(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']))
            return null;
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips)
            return null;
        foreach ($ips as $ip)
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
                return null;
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ip = strpos($ips[0], ':') !== false ? "[{$ips[0]}]" : $ips[0];
        return "$host:$port:$ip";
    }

    public static function downloadUrlToFile($remoteFile, $path, $filename = null, $image = false)
    {
        if (!$filename)
            $filename = uniqid(0,true);
        $filePath = $path . $filename;
        $fp = tmpfile();
        $url = (string)$remoteFile;
        // redirects are followed manually so that every hop passes the same public-address check
        for ($hop = 0; ; $hop++) {
            $resolve = self::publicUrlResolve($url);
            if ($resolve === null || $hop > self::DOWNLOAD_MAX_REDIRECTS)
                return false;
            ftruncate($fp, 0);
            rewind($fp);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_RESOLVE, [$resolve]);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            $result = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if ($result === false)
                return false;
            if ($code >= 300 && $code < 400 && $location !== '') {
                $url = $location;
                continue;
            }
            break;
        }
        if ($image) {
            fseek($fp, 0);
            $tmp_file = stream_get_meta_data($fp)['uri'];
            if (!self::isRasterImage($tmp_file) && is_file($tmp_file)) {
               return false;
            }
            self::convertImageSize($tmp_file);
        }
        (new FileWrapper(FileWrapper::getContainerByPath($filePath)))->writeFileResource($filePath, $fp);
        return $filename;
    }

    public static function isImage($filePath){
        $mimeType = self::getMimeType($filePath);
        if ($mimeType && in_array($mimeType, array_values(self::$MIME_TYPE_IMAGES)))
            return true;
        return false;
    }

    /**
     * Like isImage() but without SVG: an uploaded SVG can carry script and must not be accepted as a photo/logo.
     */
    public static function isRasterImage($filePath): bool
    {
        $mimeType = self::getMimeType($filePath);
        return $mimeType && $mimeType !== 'image/svg+xml' && in_array($mimeType, array_values(self::$MIME_TYPE_IMAGES), true);
    }

    /**
     * True when any extension in the file name (x.php, x.php.csv, .htaccess) is a type the web server could
     * execute or treat as configuration. Such files must never be written from user-supplied names.
     */
    public static function hasExecutableExtension(string $path): bool
    {
        $name = rtrim(strtolower(basename(str_replace('\\', '/', $path))), ". \t\r\n\0");
        if (in_array($name, ['.user.ini', 'php.ini', 'web.config'], true))
            return true;
        $parts = explode('.', $name);
        array_shift($parts);
        foreach ($parts as $ext)
            if (in_array($ext, self::EXECUTABLE_EXTENSIONS, true))
                return true;
        return false;
    }

    /**
     * Reduces a user-supplied file name to [A-Za-z0-9._-], without directories or leading dots.
     * With $force_ext the result always ends in that extension (the original one is dropped).
     */
    public static function safeFileName(string $name, string $default = 'file', ?string $force_ext = null): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = ltrim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $name), '.');
        if ($force_ext !== null)
            $name = pathinfo($name, PATHINFO_FILENAME);
        $name = substr($name, 0, 100);
        if ($name === '')
            $name = $default;
        return $force_ext === null ? $name : "$name.$force_ext";
    }

    public static function getImageType($filePath)
    {
        return array_search(self::getMimeType($filePath), self::$MIME_TYPE_IMAGES);
    }

    public static function getMimeType($filePath) {
        $realpath = realpath($filePath);
        if ( function_exists( 'mime_content_type' ) ) {
            return mime_content_type( $realpath );
        }
        if ( $realpath
            && function_exists( 'finfo_file' )
            && function_exists( 'finfo_open' )
            && defined( 'FILEINFO_MIME_TYPE' )
        ) {
            return finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $realpath );
        }
        return false;
    }

    public static function convertImageSize($imagePath, $toWidth = 250, $toHeight = 300)
    {
        list($width, $height) = getimagesize($imagePath);
        if ($width > $toWidth || $height > $toHeight) {
            list($newWidth, $newHeight) = self::calcImageSize($width, $height, $toWidth, $toHeight);
            $new_proto = imagecreatetruecolor($newWidth, $newHeight);
            $photoResource = imagecreatefromstring(file_get_contents($imagePath));
            imagecopyresampled($new_proto, $photoResource, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagejpeg($new_proto, $imagePath);
            imagedestroy($new_proto);
        }
    }

    private static function calcImageSize($width, $height, $toWidth, $toHeight)
    {
        $ratio = $width / $height;

        $setByHeight = function() use ($ratio, $toHeight) {
            $height = $toHeight;
            $width = $height * $ratio;
            return [(int)$width, (int)$height];
        };

        $setByWidth = function() use ($ratio, $toWidth) {
            $width = $toWidth;
            $height = $width / $ratio;
            return [(int)$width, (int)$height];
        };

        if (($width <= $toWidth && $height > $toHeight) || (($width > $toWidth && $height > $toHeight) && ($width < $height))) {
            return $setByHeight();
        }

        if (($width > $toWidth && $height <= $toHeight) || (($width > $toWidth && $height > $toHeight) && ($width > $height))) {
            return $setByWidth();
        }

        if (($width > $toWidth && $height > $toHeight) && ($width === $height)) {
            return $toWidth > $toHeight ? $setByHeight() : $setByWidth();
        }

        return [$width, $height];
    }

    public static function getZplStringAsPdfNew(string $zpl, $dpmm = self::BIG_PRINTER)
    {
        if (!Environment::isDev()) {
            $zpl_to_pdf = new ZplToPdfSAPI();
            $zpl_to_pdf->setZplCommands($zpl);
            $zpl_to_pdf->setDpi(self::DPMM_DPI_MAP[$dpmm] ?? self::SMALL_PRINTER_DPI);
            $pdf = $zpl_to_pdf->execute();
            if ($pdf !== null)
                return $pdf;

            Messages::addDebugInfo('!!! SAPI not converted', json_encode(['zpl' => $zpl, 'dpmm' => $dpmm]), Messages::GROUP_WAREHOUSE, Messages::TEST_PARAMETERS);
        }
        throw new \Exception('ZPL convertor not available');
    }

    public static function getZplAsPdfNew($path)
    {
        if (empty($path)) {
            return null;
        }
        $zpl = stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path));
        return self::getZplStringAsPdfNew($zpl);
    }

    public static function getCSVMimes(): array
    {
        return self::$MIME_TYPE_CSV;
    }

    public static function isCSV($type): bool
    {
        return in_array($type, self::getCSVMimes());
    }

    public static function getFileNameFromPath(string $path, int $parts = 1): string
    {
        $fileArr = explode(DIRECTORY_SEPARATOR, self::clearPath($path));

        if (count($fileArr) === 1) {
            return $fileArr[0];
        }

        $fileArr = array_reverse($fileArr);

        $fileNameArr = [];

        for ($i = 0; $i < min($parts, count($fileArr)); $i++) {
            if (empty($fileArr[$i]))
                break;
            $fileNameArr[] = $fileArr[$i];
        }

        return implode(DIRECTORY_SEPARATOR, array_reverse($fileNameArr));
    }

    /**
     * Sends a stored (user-supplied) document to the browser: a real PDF inline, anything else as a download,
     * never sniffable as HTML - so a non-PDF that got into storage cannot run script on our origin.
     */
    public static function outputStoredDocument(string $path, string $filename): void
    {
        $file_wrapper = new FileWrapper(FileWrapper::getContainerByPath($path));
        $content = (string)stream_get_contents($file_wrapper->getStream($path));
        $is_pdf = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) === 'application/pdf';
        $filename = str_replace(['"', '\\', "\r", "\n"], '', $filename);
        header('Content-Type: ' . ($is_pdf ? 'application/pdf' : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($is_pdf ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        echo $content;
    }

    public static function write($content, $path): bool
    {
        // same last line of defence as FileWrapper writes: never create a file the web server could execute
        if (self::hasExecutableExtension($path))
            return false;
        self::prepareFileDir($path);
        $fp = fopen($path, 'w');
        fwrite($fp, $content);
        return fclose($fp);
    }

    public static function moveUploadedFile($path_from, $path_to): bool
    {
        self::prepareFileDir($path_to);
        return (new FileWrapper(FileWrapper::getContainerByPath($path_to)))->writeFilePath($path_to, $path_from);
    }

    public static function copyFile($path_from, $path_to): bool
    {
        self::prepareFileDir($path_to);
        $file_wrapper_from = (new FileWrapper(FileWrapper::getContainerByPath($path_from)));
        $temp = tmpfile();
        fwrite($temp, stream_get_contents($file_wrapper_from->getStream($path_from)));

        $file_wrapper_to = (new FileWrapper(FileWrapper::getContainerByPath($path_to)));
        return $file_wrapper_to->writeFileResource($path_to, $temp);
    }

    public static function prepareFileDir($path)
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        if (!empty($dir) && !is_dir($dir))
            self::handleDir($dir);
    }

    public static function formatMB($size): float
    {
        return round($size / 1024 / 1024, 1);
    }

    public static function handleDir($dir)
    {
        if (is_dir($dir))
            return $dir;
        try {
            if (!mkdir($dir, 0777, true))
                throw new \Exception('Directory ' . $dir . ' not created');
            if (!chmod($dir, 0777))
                throw new \Exception('Permission for ' . $dir . ' not created');
        } catch (\Exception $exception) {
            Debug::sendDebugInfo('handleDir', $exception->getMessage(), Debug::TEST);
        }
        return $dir;
    }

    public static function isAccessed($path): bool
    {
        return file_exists($path) && is_readable($path) && is_writable($path);
    }

    public static function scanDir($dir): array
    {
        if (!is_dir($dir))
            return [];
        $list = scandir($dir);
        return array_filter($list, function($item) {
            return !in_array($item, ['.', '..']);
        });
    }

    public static function getDirs($dir): array
    {
        return array_filter(array_map(function($item) use ($dir) {
            return $dir . DIRECTORY_SEPARATOR . $item;
        }, self::scanDir($dir)), function($item) {
            return is_dir($item);
        });
    }

    public static function getFiles($dir): array
    {
        return array_filter(array_map(function($item) use ($dir) {
            return $dir . DIRECTORY_SEPARATOR . $item;
        }, self::scanDir($dir)), function($item) {
            return !is_dir($item);
        });
    }

    public static function getFirstFile($dir): ?string
    {
        static $static_files = [];
        $file = false;
        if ($h = opendir($dir)) {
            while (($file = readdir($h)) !== false) {
                if ($file != '.' && $file != '..' && !array_key_exists($file, $static_files)) {
                    break;
                }
            }
            closedir($h);
        }
        if (!empty($file)) {
            $static_files[$file] = 1;
            return $dir . $file;
        } else
            return null;
    }

    public static function loadFile($files)
    {
        switch ($files['error']) {
            case 0:
            case 4:
                break;
            default:
                return [false, ErrorHandler::errorFileUpload($files['error'])];
        }
        $name = empty($files['name']) ? '' : self::safeFileName($files['name']);
        if (!empty($name))
            if (!File::moveUploadedFile($files['tmp_name'],TEMP_STORAGE . DIRECTORY_SEPARATOR . $name))
                return [false, 'Access denied'];
        return [true, TEMP_STORAGE . DIRECTORY_SEPARATOR . $name];
    }

    public static function getImage($filename, $ext = null)
    {
        if (is_null($ext))
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'bmp')
            return imagecreatefrombmp($filename);
        elseif ($ext === 'png')
            return imagecreatefrompng($filename);
        elseif (in_array($ext, ['jpg', 'jpeg']))
            return imagecreatefromjpeg($filename);
        elseif ($ext === 'gif')
            return imagecreatefromgif($filename);
        else
            return false;
    }

    public static function convert2PNG(string $filename, $new_filename = null, string $ext = '')
    {
        if (!$ext)
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'png')
            return true;
        $image = self::getImage($filename, $ext);
        if ($image === false)
            return false;
        if (is_null($new_filename))
            return true;
        return imagepng($image, $new_filename);
    }

    public static function createImageZpl(string $filename, int $width, float $koeff)
    {
        $image = self::getImage($filename);
        if ($image === false)
            return null;
        $image = imagescale($image, $width);
        $height = imagesy($image);
        $line_bytes = $width >> 3;
        $data = $height*$line_bytes;
        $data = "$data,$data,$line_bytes,";
        for ($i = 0; $i < $height; $i++) {
            $bytes = '';
            for ($j = 0; $j < imagesx($image); $j++) {
                $col = imagecolorat($image, $j, $i);
                $rgb = imagecolorsforindex($image, $col);
                $b = round(($rgb['red'] + $rgb['green'] + $rgb['blue'])/3);
                $bytes .= (int)($b < 255*$koeff);
                if (strlen($bytes) === 8) {
                    $data .= sprintf("%02X", bindec($bytes));
                    $bytes = '';
                }
            }
        }
        return $data;
    }

    private static function getAngle($point1, $point2): float
    {
        return atan(($point2['y'] - $point1['y'])/($point2['x'] - $point1['x']));
    }

    private static function getPoint($x, $y): array
    {
        return ['x' => $x, 'y' => $y];
    }

    private static function getRotated($point, $center, $alpha): array
    {
        return self::getPoint(
            ($point['x'] - $center['x']) * cos(-$alpha) - ($point['y'] - $center['y']) * sin(-$alpha) + $center['x'],
            ($point['x'] - $center['x']) * sin(-$alpha) + ($point['y'] - $center['y']) * cos(-$alpha) + $center['y']
        );
    }

    private static function fixingPoint($point, $delta_W, $delta_H): array
    {
        $point['x'] += $delta_W/2;
        $point['y'] += $delta_H/2;
        return self::getPoint(round($point['x']), round($point['y']));
    }

    public static function getSKUFromPhoto(string $filename, string $data_coordinates)
    {
        if (!is_file($filename) || empty($data_coordinates))
            return false;
        $coords = array_map(function ($item) {
            return explode(',', $item);
        }, explode('|', $data_coordinates));
        $coords = array_map(function ($item) {
            return self::getPoint($item[0], $item[1]);
        }, $coords);
        $alpha = self::getAngle($coords[0], $coords[1]);
        $deg = rad2deg($alpha);
        $image = imagecreatefromjpeg($filename);
        $width = imagesx($image);
        $height = imagesy($image);
        $image = imagerotate($image, $deg, 0);
        $o = self::getPoint($width/2, $height/2);
        $coords = array_map(function ($item)  use($o, $alpha) {
            return self::getRotated($item, $o, $alpha);
        }, $coords);
        $width_new = imagesx($image);
        $height_new = imagesy($image);
        $coords = array_map(function ($item)  use($width_new, $width, $height_new, $height) {
            return self::fixingPoint($item,  $width_new - $width, $height_new - $height);
        }, $coords);
        $min_X = min($coords[0]['x'], $coords[3]['x']);
        $min_Y = min($coords[0]['y'], $coords[1]['y']);
        $max_X = max($coords[1]['x'], $coords[2]['x']);
        $max_Y = max($coords[2]['y'], $coords[3]['y']);
        $rect = ['x' => $min_X, 'y' => $min_Y, 'width' => $max_X - $min_X, 'height' => $max_Y - $min_Y];
        $image = imagecrop($image, $rect);
        imagejpeg($image, $filename);
        imagedestroy($image);
        return true;
    }

    public static function prepareFileName(string $file_name)
    {
        return addslashes(str_replace('/', '-', $file_name));
    }

    public static function getAllFiles(string $files_path, bool $recursive, array &$files = []) {
        foreach (scandir($files_path) as $file) {
            if ($file == '.' || $file == '..')
                continue;
            $path = "$files_path/$file";
            if (is_file($path)) {
                $files[] = $path;
            } elseif (is_dir($path)) {
                if (!$recursive)
                    continue;
                self::getAllFiles($path, true, $files);
            }
        }
    }

    /**
     * True if $path, once "." and ".." segments are resolved, still lies within $base (or
     * equals it). Use this whenever a filesystem path is built by concatenating a fixed
     * $base with a request-controlled suffix, to reject a "../" traversal that would escape
     * the intended folder before touching the filesystem.
     */
    public static function isPathWithin(string $path, string $base): bool
    {
        $path = self::normalizePath($path);
        $base = rtrim(self::normalizePath($base), '/\\');
        return $path === $base || strpos($path, $base . DIRECTORY_SEPARATOR) === 0;
    }

    public static function normalizePath(string $path)
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = array_reduce(explode('\\', $path), function ($a, $b) {
                if ($a === null)
                    return $b;
                if ($b === "" || $b === ".")
                    return $a;
                if ($b === "..")
                    return substr($a, 0, strripos($a,'\\'));
                return preg_replace("/\\+/", "\\", "$a\\$b");
            });
            return $path;
        }
        $path = array_reduce(explode(DIRECTORY_SEPARATOR, $path), function ($a, $b) {
            if ($a === null)
                $a = "/";
            if ($b === "" || $b === ".")
                return $a;
            if ($b === "..")
                return dirname($a);
            return preg_replace("/\/+/", "/", "$a/$b");
        });
        return $path;
    }
}
