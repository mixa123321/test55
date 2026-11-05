<?php

namespace app\core\helpers\scan;

use app\core\helpers\View;

class Scan
{
    const TYPE__SKU = 1;
    const TYPE__LOCATION = 2;
    const TYPE__ORDER = 4;

    private $scan_placeholder;
    private $scan_id;
    private $scan_style;
    private $scan_name;
    private $scan_value;
    private $scan_type;
    private $scan_autofocus;
    private $btn_id;
    private $btn_title;
    private $btn_type;
    private $btn_icon;
    private $btn_data;
    private $btn_class;
    private $btn_style;
    private $btn_onclick;

    /** @return mixed|string */
    public function getScanId()
    {
        return $this->scan_id;
    }

    /** @return mixed|string */
    public function getScanStyle()
    {
        return $this->scan_style;
    }

    /** @return mixed|string */
    public function getScanName()
    {
        return $this->scan_name;
    }

    /** @return mixed|string */
    public function getScanValue()
    {
        return $this->scan_value;
    }

    /** @return mixed|string */
    public function getBtnId()
    {
        return $this->btn_id;
    }

    /** @return mixed|string */
    public function getBtnTitle()
    {
        return $this->btn_title;
    }

    /** @return mixed|string */
    public function getBtnIcon()
    {
        return $this->btn_icon;
    }

    /** @return string */
    public function getBtnData(): string
    {
        return $this->btn_data;
    }

    /** @return mixed|string */
    public function getBtnClass()
    {
        return $this->btn_class;
    }

    /** @return mixed|string */
    public function getBtnStyle()
    {
        return $this->btn_style;
    }

    /** @return mixed|string */
    public function getBtnOnclick()
    {
        return $this->btn_onclick;
    }

    /** @return mixed|string */
    public function getScanPlaceholder()
    {
        return $this->scan_placeholder;
    }

    /** @return int */
    public function getScanType()
    {
        return $this->scan_type;
    }

    public function __construct(array $scan_data = [], array $btn_data = [])
    {
        $this->scan_id = $scan_data['id'] ?? uniqid('scan_id_');
        $this->scan_placeholder = $scan_data['placeholder'] ?? '';
        $this->scan_style = $scan_data['style'] ?? '';
        $this->scan_name = $scan_data['name'] ?? '';
        $this->scan_value = $scan_data['value'] ?? '';
        $this->scan_type = $scan_data['type'] ?? 0;
        $this->scan_autofocus = $scan_data['autofocus'] ?? true;
        $this->btn_id = $btn_data['id'] ?? uniqid('scan_btn_id_');
        $this->btn_title = $btn_data['title'] ?? 'Scan';
        $this->btn_type = $btn_data['type'] ?? 'submit';
        $this->btn_icon = $btn_data['icon'] ?? 'fas fa-fw fa-search';
        $this->btn_data = $this->handleBtnData($btn_data['data'] ?? []);
        $this->btn_class = $btn_data['class'] ?? '';
        $this->btn_style = $btn_data['style'] ?? '';
        $this->btn_onclick = $btn_data['onclick'] ?? '';
    }

    private function handleBtnData($data): string
    {
        $btn_data = '';
        foreach ($data as $btn_index => $btn_val)
            $btn_data .= 'data-' . $btn_index . '="' . View::toHtml($btn_val) . '"' . PHP_EOL;
        return $btn_data;
    }

    public function render()
    {
        echo $this->getTemplate();
    }

    public function getTemplate(): string
    {
        return '<div class="form-group row w-100 my-1 mx-0 px-0 js-scan-barcode" data-scan-type="' . $this->scan_type . '">
                    <div class="col px-0">
                        <input class="form-control"
                               style="' . $this->scan_style . '"
                               type="text"
                               id="' . $this->scan_id . '"
                               ' . (!empty($this->scan_name) ? ('name="' . $this->scan_name . '"') : '') . '
                               ' . (!empty($this->scan_value) ? ('value="' . $this->scan_value . '"') : '') . '
                               placeholder="' . $this->scan_placeholder . '"
                               ' . ($this->scan_autofocus ? 'autofocus' : '') . '
                               autocomplete="off"
                        >
                    </div>
                    <div class="col-auto align-self-center pl-2 pr-0">
                        <button id="' . $this->btn_id . '"
                                class="btn btn-success btn-icon-split ' . $this->btn_class . '"
                                ' . $this->btn_data . '
                                style="' . $this->btn_style . '"
                                ' . (!empty($this->btn_onclick) ? ('onclick="' . $this->btn_onclick . '"') : '') . '
                                type="' . $this->btn_type . '">
                            <span class="icon text-white-50">
                                <i class="' . $this->btn_icon . '"></i>
                            </span>
                            <span class="text d-none d-md-block">' . $this->btn_title . '</span>
                        </button>
                    </div>
                </div>';
    }
}