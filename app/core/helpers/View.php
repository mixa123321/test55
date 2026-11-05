<?php

namespace app\core\helpers;

use app\core\models\TokensTranslations;
use app\core\Router;

class View
{
    private static $flash;
    private static $template_tokens = [];

    private static $db_logs = [];

    private static function initFlash()
    {
        if (empty($_SESSION['flash_messages']))
            $_SESSION['flash_messages'] = [];
        self::$flash = &$_SESSION['flash_messages'];
    }

    public static function addFlash($name, $type, $message)
    {
        self::initFlash();
        if (empty(self::$flash[$name]))
            self::$flash[$name] = [];
        self::$flash[$name][] = [
            'type' => $type,
            'message' => $message
        ];
    }

    public static function setTemplateTokens($tokens)
    {
        self::$template_tokens = $tokens;
    }

    public static function addTokens($tokens)
    {
        self::$template_tokens = array_merge(self::$template_tokens, $tokens);
    }

    public static function getTranslate($token)
    {
        return TokensTranslations::getTranslate(self::$template_tokens, $token);
    }

    public static function getBackend($template)
    {
        return BACKEND_VIEW_PATH . DIRECTORY_SEPARATOR . $template;
    }

    public static function render($vars = [], $view = '', $template = 'default')
    {
        extract($vars);
        $path = self::getBasePath();
        $template_path = $path . DIRECTORY_SEPARATOR . "{$template}_template.php";
        ob_start();
        if (is_file($template_path))
            require_once $template_path;
        else
            require_once $path . DIRECTORY_SEPARATOR . "default_template.php";
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    public static function isActiveMenu($str)
    {
        return preg_match("/^\/{$str}(\/.+)?$/", HTTP::getRequestUri()) ? 'active' : '';
    }

    public static function outputAllFlash()
    {
        self::initFlash();
        foreach (self::$flash as $flash_name => $flash_data) {
            foreach ($flash_data as $val) {
                echo "<div id='main-flash-errors' class='alert alert-" . self::toHtml($val['type']) . "' style='possition: relative'>
                          <span class='badge text-xs m-0 p-0' style='position: absolute; bottom: 0; right: 0; z-index: 1; opacity: 0.25;'>$flash_name</span>";
                if (!is_array($val['message']))
                    $val['message'] = [$val['message']];
                $i = 1;
                foreach ($val['message'] as $message) {
                    echo self::toHtml($message) . ($i != count($val['message']) ? '<hr class="my-1"/>' : '');
                    $i++;
                }
                echo "</div>";
            }
            unset(self::$flash[$flash_name]);
        }
    }

    public static function toHtml($str)
    {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapes stored history comments, which mix raw data with a few fixed markup fragments
     * (line breaks, the "hashed" badge). Entities are decoded first, so parts that were
     * already escaped when the comment was written are not shown double-escaped.
     */
    public static function toHtmlHistory($str): string
    {
        $html = self::toHtml(html_entity_decode((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return (string)str_ireplace(
            ['&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;', '&lt;span class=&quot;badge badge-danger&quot;&gt;', '&lt;/span&gt;'],
            ['<br/>', '<br/>', '<br/>', '<span class="badge badge-danger">', '</span>'],
            $html
        );
    }

    /**
     * Escaped href value for an untrusted URL: only http(s) and root-relative paths are kept.
     */
    public static function toHref($url): string
    {
        $url = trim((string)$url);
        if (!preg_match('~^(https?://|/(?![/\\\\]))~i', $url))
            return '#';
        return self::toHtml($url);
    }

    public static function jsonToHtml($json_str, $separate = '; ', $html = true)
    {
        if (!is_array($json_str)) {
            $json_str = json_decode($json_str, true);
            if (!is_array($json_str))
                $json_str = [$json_str];
        }
        $msg = implode($separate, $json_str);
        return $html ? self::toHtml($msg) : $msg;
    }

    public static function toJs($value)
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    public static function btnModalDismiss($text = 'Cancel', $classStyle = 'secondary'): string
    {
        return '<button class="btn btn-' . $classStyle . ' btn-icon-split btn-no-width" data-dismiss="modal" type="button">
                    <span class="text">' . self::toHtml($text) . '</span>
                </button>';
    }

    private static function pageIn($name, $disable = true, $url = '', $start = false, $active = false)
    {
        $display_class = "d-none d-sm-block";
        if($name == "<i class='fas fa-angle-left'></i>" || $name == "<i class='fas fa-angle-right'></i>") {
            //Show only < and > on small screens
            $display_class = "";
        }
        return "<li class='$display_class page-item" . ($disable ? ' disabled' : '') . ($active ? ' active' : '') . "'>
                <a class='page-link'" . ((!$disable && !empty($url)) ? " href='$url'" : '') . ($start ? ' tabindex=\'-1\'' : '') . ">$name</a>
            </li>";
    }

    public static function pagination($path, $start, $end, $current, $size = 10)
    {
        if ((int)$current < (int)$start)
            $current = $start;
        if ((int)$current > (int)$end)
            $current = $end;

        echo '<div class=\'row justify-content-center mt-2 mb-2 p-0\'><nav><ul class=\'pagination m-0\'>';
        echo self::pageIn('<i class=\'fas fa-angle-double-left\'></i>', $current == $start, "{$path}c=$start", true);
        echo self::pageIn('<i class=\'fas fa-angle-left\'></i>',$current == $start, "{$path}c=" . ($current - 1));
        $ellipsis = $current <= 3;
        for ($i = $start; $i <= $end; $i++) {
            if ($i < $current - 2) {
                if (!$ellipsis) {
                    echo self::pageIn('<i class=\'fas fa-fw fa-ellipsis-h\'></i>');
                    $ellipsis = true;
                }
            } else if ($i > $current + 2) {
                if ($ellipsis) {
                    echo self::pageIn('<i class=\'fas fa-fw fa-ellipsis-h\'></i>');
                    $ellipsis = false;
                }
            } else
                echo self::pageIn($i, false, ($i != $current ? "{$path}c=$i" : ''), false, $i == $current);
        }
        echo self::pageIn('<i class=\'fas fa-angle-right\'></i>',$current == $end, "{$path}c=" . ($current + 1));
        echo self::pageIn('<i class=\'fas fa-angle-double-right\'></i>', $current == $end, "{$path}c=$end");
        echo '</ul></nav></div>';
    }

    public static function simplePagination($path, $current, $next = true)
    {
        if ((int)$current < 1)
            $current = 1;

        echo '<div class=\'row justify-content-center mt-2 mb-2 p-0\'><nav><ul class=\'pagination m-0\'>';
        echo self::pageIn('<i class=\'fas fa-angle-double-left\'></i>', (int)$current === 1, "{$path}c=1", true);
        echo self::pageIn('<i class=\'fas fa-angle-left\'></i>',(int)$current === 1, "{$path}c=" . ($current - 1));

        $i = $current <= 4 ? 1 : $current - 4;
        while ($i < $current) {
            $page = $i++;
            echo self::pageIn((int)$page, false, "{$path}c=$page");
        }

        echo self::pageIn($current, true, null, false, true);
        echo self::pageIn('<i class=\'fas fa-angle-right\'></i>', !$next, "{$path}c=" . ($current + 1));
        echo '</ul></nav></div>';
    }

    public static function include_js($dir, $file)
    {
        $full_path = ROOT_PATH . "$dir/$file";
        if(is_file($full_path)) {
            $md5 = md5_file($full_path);
            return "<script src='$dir/$file?v=$md5'></script>";
        }
        return '';
    }

    public static function include_js_with_params($dir, $file, array $params = [])
    {
        $full_path = ROOT_PATH . "$dir/$file";
        if(is_file($full_path)) {
            $md5 = md5_file($full_path);
            $scriptParams = '';
            if (!empty($params)) {
                $scriptParams .= "<script>\n";
                foreach ($params as $let => $value) {
                    $scriptParams .= '      let ' . $let . " = " . self::toJs($value) . ";\n";
                }
                $scriptParams .= "</script>\n";
            }

            return $scriptParams . "<script src='$dir/$file?v=$md5'></script>";
        }
        return '';
    }

    public static function include($file, array $vars)
    {
        $path = self::getBasePath();
        $full_path = $path . DIRECTORY_SEPARATOR . $file;
        if (!is_file($full_path))
            throw new \Exception("File {$full_path} not found");
        extract($vars);
        ob_start();
        include $full_path;
        return ob_get_clean();
    }

    public static function include_css($dir, $file)
    {
        $full_path = ROOT_PATH . "$dir/$file";
        if(is_file($full_path)) {
            $md5 = md5_file($full_path);
            return "<link href='$dir/$file?v=$md5' rel='stylesheet'/>";
        }
        return '';
    }

    public static function addToDbLogging($query, $current_db, $affected_rows, $query_time, $error)
    {
        self::$db_logs[] = [
            'query' => $query,
            'current_db' => $current_db,
            'affected_rows' => $affected_rows,
            'query_time' => $query_time,
            'error' => $error
        ];
    }

    public static function outputDbLogging()
    {
        echo '<script>' . PHP_EOL;
        foreach (self::$db_logs as $db_log)
            echo 'console.debug(' . self::toJs($db_log) . ');' . PHP_EOL;
        echo '</script>' . PHP_EOL;
    }

    // $escape = false only for input that is already HTML-safe (escaped data with trusted markup)
    public static function toHtmlConvertWithPrimaryBarcode($str, bool $escape = true)
    {
        $arrayWords = explode(',', (string)$str);
        if ($escape)
            $arrayWords = array_map([self::class, 'toHtml'], $arrayWords);
        if (isset($arrayWords[0]) && trim($arrayWords[0]))
            $arrayWords[0] = '<span class="primary_barcode">' . $arrayWords[0] . '</span>';

        return '<span class="barcodes_list">'.implode(',', $arrayWords).'</span>';
    }

    private static function getBasePath()
    {
        $const = strtoupper(Router::$app) . '_VIEW_PATH';
        if (defined($const))
            return constant($const);
        return FRONTEND_VIEW_PATH;
    }
}
