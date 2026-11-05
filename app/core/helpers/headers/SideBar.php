<?php

namespace app\core\helpers\headers;

use app\core\interfaces\HtmlComponent;
use app\core\models\Routes;
use app\core\models\TokensTranslations;
use app\core\Permission;

abstract class SideBar implements HtmlComponent
{
    protected $items = [];
    protected $heads = [];
    private static $routes = [];
    private $logo;
    private $addition;
    private $title_items = [];

    abstract public function render();

    protected function setHead($logo, $addition = '')
    {
        $this->logo = $logo;
        $this->addition = $addition;
    }

    private function getHead(): string
    {
        return "
            <a class=\"sidebar-brand d-flex align-items-center justify-content-center\" href=\"/\">
                <div class=\"sidebar-brand-icon\">
                    <img style=\"max-width:100%\" src=\"" . COMMON_IMG . DIRECTORY_SEPARATOR . $this->logo . "\" />
                    {$this->addition}
                </div>
            </a>";
    }

    public function put(): string
    {
        $this->render();
        return $this->getHead() . implode("\n", $this->items);
    }

    protected function changeTitle($title)
    {
        return TokensTranslations::getTranslate($this->title_items, $title);
    }

    protected function setTitleItems($lang)
    {
        $this->title_items = TokensTranslations::getMenuTokens($lang);
    }

    private function getSubMenu($item_class, $items): string
    {
        $html_res = <<<HTMLSMENU
                        <div id="collapse$item_class" class="collapse" aria-labelledby="heading$item_class" data-parent="#accordionSidebar">
                            <div class="bg-white py-2 collapse-inner">
                    HTMLSMENU;
        foreach ($items as $title => $path)
            if ($path[1]) {
                if ($path[0] === '-') {
                    $html_res .= "<hr class=\"my-2\">";
                    continue;
                }
                if (array_key_exists($path[0], self::$routes))
                    continue;
                self::$routes[$path[0]] = true;
                $html_res .= "<a class=\"collapse-item\"" . (!empty($path[2]) ? "" : " style='color:#007ab7'") . " href=\"/{$path[0]}\">" . $this->changeTitle($title) . "</a>";
            }
        $html_res .= "</div>\n</div>";
        return $html_res;
    }

    protected function addItem($title, $icon, $sub_menu = '', $active = false, $href = null) : SideBar
    {
        $active = $active ? " active" : "";
        $title_short = str_replace(" ", "", $title);
        $href = $href ?: ucfirst($title_short);
        if (is_array($sub_menu))
            $is_sub = " collapsed\" href=\"/dashboard\" data-toggle=\"collapse\" data-target=\"#collapse$title_short\" aria-expanded=\"true\" aria-controls=\"collapse$title_short";
        else {
            if (!empty($href) && array_key_exists($href, self::$routes))
                return $this;
            $is_sub = "\" href=\"/$href";
            self::$routes[$href] = true;
        }
        $html_res = "
            <!-- Nav Item - $title Collapse Menu -->
            <li class=\"nav-item$active\">
                <a class=\"nav-link$is_sub\">
                    <i class=\"fas fa-fw fa-$icon\"></i>
                    <span>" . $this->changeTitle($title) . "</span>
                </a>";
        if (is_array($sub_menu))
            $html_res .= $this->getSubMenu($title_short, $sub_menu);
        $html_res .= "</li>";
        $this->items[] = $html_res;
        return $this;
    }

    protected function addSeparator() : SideBar
    {
        $this->items[] = "<!-- Divider -->\n<hr class=\"sidebar-divider\">";
        return $this;
    }

    protected function addHeading($head) : SideBar
    {
        if (!in_array($head, $this->heads)) {
            $this->heads[] = $head;
            $this->items[] = "<!-- Heading -->\n<div class=\"sidebar-heading\">" . $this->changeTitle($head) . "</div>\n";
        }
        return $this;
    }

    protected function createSubMenuArray(array $sub_items, ?Permission $permission): array
    {
        $res = [];
        $i = 0;
        foreach ($sub_items as $url => $route_data) {
            if (!empty($br) && !empty($res) && !is_int(array_key_last($res))) {
                $res[$i++] = ['-', true];
                $br = false;
            }
            if (is_int($url)) {
                $br = true;
                continue;
            }
            $route_object = Routes::find($url);
            if (empty($route_object))
                continue;
            $access = is_null($route_data[1]) ? $permission->isAccess($route_object) : $route_data[1];
            if (!$access)
                continue;
            $res[$route_data[0]] = [
                $route_object->getName(),
                $access
            ];
        }
        if (!empty($res) && is_int(array_key_last($res)))
            array_pop($res);
        return $res;
    }
}