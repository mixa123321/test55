<?php

namespace app\core\helpers\modal;

use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\interfaces\HtmlComponent;

abstract class Modal implements HtmlComponent
{
    const SIZE_NORMAL       = '';
    const SIZE_LG           = 'modal-lg';
    const SIZE_XL           = 'modal-xl';
    const TYPE_DEFAULT      = '';
    const TYPE_BUTTON       = 'button';
    const TYPE_SUBMIT       = 'submit';
    const BUTTON_CANCEL     = 0;
    const BUTTON_DONE       = 1;
    const BUTTON_CONFIRM    = 2;
    const METHOD_DEFAULT    = '';
    const METHOD_GET        = 'get';
    const METHOD_POST       = 'post';
    const ACTION_DEFAULT    = '';

    const SIZES = [
        self::SIZE_NORMAL,
        self::SIZE_LG,
        self::SIZE_XL,
    ];

    const TYPES = [
        self::TYPE_DEFAULT,
        self::TYPE_BUTTON,
        self::TYPE_SUBMIT,
    ];

    const METHODS = [
        self::METHOD_DEFAULT,
        self::METHOD_GET,
        self::METHOD_POST,
    ];

    protected $id;
    protected $title;
    protected $type;
    protected $secondary;
    protected $content = [];
    protected $content_attrs = [];
    protected $buttons = [];
    protected $custom_buttons = [];
    protected $form = [];

    static $global_variables = [];

    public function __construct($id, $title = null, $type = self::SIZE_NORMAL, $secondary = false)
    {
        $type = strtolower($type);
        if (!is_string($id))
            throw new \Exception('ID is absent');
        $this->id = "{$id}Modal";
        $this->title = $title;
        $this->type = in_array($type, self::SIZES) ? $type : self::SIZE_NORMAL;
        $this->secondary = $secondary;
        return $this;
    }

    public function getSelector()
    {
        return '#' . $this->id;
    }

    public static function addGlobalVariables($variables)
    {
        self::$global_variables = array_merge(self::$global_variables, $variables);
    }

    public static function addGlobalVariable($name, $value)
    {
        self::$global_variables[$name] = $value;
    }

    public function addContent($content)
    {
        if (!empty($content)) {
            if (is_array($content))
                $this->content = array_merge($this->content, $content);
            else
                $this->content[] = $content;
        }
        return $this;
    }

    public function includeFile($filepath, $variables = [])
    {
        $variables['modal'] = $this;
        $variables = array_merge(self::$global_variables, $variables);
        return $this->addContent(View::include($filepath, $variables));
    }

    public function put(): string
    {
        $body = Html::div($this->content, $this->content_attrs + ['class' => 'modal-body']);
        $footer = Html::div($this->custom_buttons ?: $this->getButtons(), ['class' => 'modal-footer']);
        $body_footer = empty($this->form) ? [$body, $footer] : [Html::tag('form', $body . $footer, $this->form)];
        $secondary_content = '';
        if ($this->secondary)
            $secondary_content = "<script>
                $('#{$this->id}').on('hidden.bs.modal', function() {
                    document.body.classList.add('modal-open');
                });</script>";
        return Html::div(
            Html::div(
                Html::div(
                    array_merge([
                                    Html::div(
                                        [
                                            Html::tag('h5', $this->title, ['class' => 'modal-title mb-0', 'id' => 'modalLabel']),
                                            Html::tag(
                                                'button',
                                                Html::tag('span', '&times;', ['aria-hidden' => 'true']),
                                                ['type' => 'button', 'class' => 'close', 'data-dismiss' => 'modal', 'aria-label' => 'Close']),
                                        ],
                                        ['class' => 'modal-header'])
                                ], $body_footer),
                    ['class' => 'modal-content' . ($this->secondary ? ' border border-danger' : '')] + ($this->secondary ? ['style' => 'border-width: 3px !important;'] : [])),
                ['class' => "modal-dialog {$this->type}", 'role' => 'document']),
            ['class' => 'modal fade', 'id' => $this->id, 'tabindex' => -1, 'role' => 'dialog', 'aria-labelledby' => 'modalLabel', 'aria-hidden' => 'true']) . $secondary_content;
    }

    public function safePut(): string
    {
        return sprintf(
            '<script type="text/template" id="%1$s_template">%2$s</script><script id="%1$s_generator">$(function() {'
            . '$(\'body\').append(atob($(\'#%1$s_template\').html()));$(\'#%1$s_template\,#%1$s_generator\').remove();'
            . '});</script>',
            $this->id,
            base64_encode($this->put())
        );
    }

    public function show()
    {
        return "$('{$this->getSelector()}').modal('show');";
    }

    public function showWithParameteres(array $params)
    {
        $strings = [];
        foreach ($params as $key => $value) {
            $strings[] = $this->jsSetBodyAttribute($key, $value);
        }

        return implode($strings) . $this->show();
    }

    public function hide()
    {
        return "$('{$this->getSelector()}').modal('hide');";
    }

    public function jsSetBodyAttribute($name, $jsValue)
    {
        if ($jsValue === null)
            return "$('{$this->getSelector()} .modal-body').removeAttr('{$name}');";
        if ('data-' === substr($name, 0, 5))
            return "$('{$this->getSelector()} .modal-body').data('$name', $jsValue);";
        return "$('{$this->getSelector()} .modal-body').attr('$name', $jsValue);";
    }

    public function jsGetBodyAttribute($name)
    {
        if ('data-' === substr($name, 0, 5))
            return "$('{$this->getSelector()} .modal-body').data('$name')";
        return "$('{$this->getSelector()} .modal-body').attr('$name')";
    }

    protected function addButton($text, $type = self::TYPE_BUTTON, $class = self::BUTTON_CANCEL, $options = [])
    {
        $type = strtolower($type);
        $this->buttons[] = [
            'value' => $text,
            'type' => in_array($type, self::TYPES) ? $type : self::TYPE_BUTTON,
            'class' => 'btn btn-' . (($class & self::BUTTON_DONE) == self::BUTTON_DONE ? '' : 'outline-') . 'orange',
            'data-dismiss' => ($class & self::BUTTON_CONFIRM) == self::BUTTON_CONFIRM ? 'modal' : '',
            'options' => $options
        ];
    }

    public function getButtons()
    {
        $buttons = '';
        foreach ($this->buttons as $button) {
            if (!empty($button['options']['class'])) {
                $button['class'] .= ' ' . $button['options']['class'];
                unset($button['options']['class']);
            }
            if (!empty($button['data-dismiss']))
                $button['options']['data-dismiss'] = $button['data-dismiss'];
            if (!empty($button['type']))
                $button['options']['type'] = $button['type'];
            $button['options']['class'] = $button['class'];
            $buttons .= Html::tag('button', $button['value'], $button['options']);
        }
        return $buttons;
    }

    public function addCustomButtons($content)
    {
        if (!empty($content)) {
            if (is_array($content))
                $this->custom_buttons = array_merge($this->custom_buttons, $content);
            else
                $this->custom_buttons[] = $content;
        }
        return $this;
    }

    public function addForm($action = self::ACTION_DEFAULT, $method = self::METHOD_DEFAULT, $options = [])
    {
        $this->form = [];
        if (!empty($action))
            $this->form['action'] = $action;
        $method = strtolower($method);
        if (!in_array($method, self::METHODS))
            $method = self::METHOD_DEFAULT;
        if (!empty($method))
            $this->form['method'] = $method;
        if (!empty($options))
            $this->form = array_merge($this->form, $options);
        return $this;
    }

    public function addBodyAttributes($options)
    {
        if (!empty($options)) {
            if (is_array($options))
                $this->content_attrs = array_merge($this->content_attrs, $options);
            else
                $this->content_attrs[] = $options;
        }
        return $this;
    }
}