<?php

namespace app\core\helpers;

class Html
{
    public const TYPE_BUTTON__PRIMARY = 'primary';
    public const TYPE_BUTTON__CANCEL = 'cancel';

    public static function beginTag($name, $options = [])
    {
        $tag_content = "<{$name}";
        if ($options !== []) {
            $tag_content .= ' '.self::renderTagAttributes($options);
        }
        $tag_content .= '>';
        return $tag_content;
    }

    public static function endTag($name)
    {
        return "</{$name}>";
    }

    private static function renderTagAttributes($options)
    {
        $attributes = [];
        foreach ($options as $key => $value) {
            if ($key === 'style' && \is_array($value)) {
                $values = [];
                foreach ($value as $t => $v) {
                    $values[] = "{$t}: {$v}";
                }
                $value = implode('; ', $values);
            }
            if (\is_array($value)) {
                $value = json_encode($value);
            }
            $attributes[] = "{$key}=\"" . View::toHtml($value) . "\"";
        }
        return implode(' ', $attributes);
    }

    public static function tag($name, $content = null, $options = [])
    {
        $single_tags = ['br', 'hr', 'input', 'img'];
        $tag_content = self::beginTag($name, $options);
        if (\in_array($name, $single_tags, true) === false) {
            $tag_content .= $content;
            $tag_content .= self::endTag($name);
        }
        return $tag_content;
    }

    public static function div($content = null, $options = [])
    {
        return Html::tag('div', is_array($content) ? implode($content) : $content, $options);
    }

    public static function setUp($checks, $is_virtual)
    {
        $items = ['Users', 'SKUs', 'Packaging'];
        if ($is_virtual)
            $checks = array_slice($checks, 0, 2);
        $res = [];
        $num = 0;
        foreach ($checks as $key => $val) {
            $res[] = "<i class=\"fas fa-fw fa-" . ($val ? "check" : "times") . " text-" . ($val ? "success" : "danger") . "\"></i> " . $items[$key];
            if ($val)
                $num++;
        }
        return "<b>" . (int)($num*100/count($checks)) . "% Complete</b><br/><span class='text text-xs'>" . implode("<br/>", $res) . "</span>";
    }

    public static function img($src, $options = [])
    {
        $options['src'] = $src;
        return self::tag('img', null, $options);
    }

    public static function dropDownList($name, $select_options = [], $selected_value = null, $options = [])
    {
        $rendered_options = [];
        if (!isset($options['class']))
            $options['class'] = 'form-control form-control-lg custom-select';
        foreach ($select_options as $key => $value) {
            $option_options = ['value' => $key];
            if (\is_string($selected_value) && $selected_value === (string)$key) {
                $option_options['selected'] = 'selected';
            } elseif (($options['multiple'] ?? false) && \is_array($selected_value) && \in_array($key, $selected_value)) {
                $option_options['selected'] = 'selected';
            }
            if (isset($options['encode']) ? $options['encode'] : true) {
                unset($options['encode']);
                $value = View::toHtml($value);
            }
            $rendered_options[] = self::tag('option', $value, $option_options);
        }
        $options['name'] = $name;
        return self::tag('select', implode('', $rendered_options), $options);
    }

    public static function dropDownListSelectpicker($name, $select_options = [], $selected_value = null, $options = []) {
        $rendered_options = [];
        foreach ($select_options as $key => $value) {
            $option_options = ['value' => $key];
            if ($selected_value) {
                if (
                    (\is_string($selected_value) && $selected_value === (string)$key) ||
                    (\is_array($selected_value) && \in_array($key, $selected_value))
                ) {
                    $option_options['selected'] = 'selected';
                }
            }
            if (isset($options['encode']) ? $options['encode'] : true) {
                unset($options['encode']);
                $value = View::toHtml($value);
            }
            $rendered_options[] = self::tag('option', $value, $option_options);
        }
        $options['name'] = $name;
        return self::tag('select', implode('', $rendered_options), $options);
    }

    public static function textInput($name, $value = null, $options = [])
    {
        unset($options['encode']);
        $options = array_merge($options, [
            'name'  =>  $name,
            'value' =>  $value
        ]);
        return self::tag('input', null, $options);
    }

    public static function checkboxInput($name, $value = null, $checked = false, $options = []) {
        unset($options['encode']);
        if ($checked) {
            $options['checked'] = 'checked';
        }
        $options = array_merge($options, [
            'name'  =>  $name,
            'value' =>  $value,
            'type'  =>  'checkbox'
        ]);
        return self::tag('input', null, $options);
    }

    public static function checkboxInputList($name, $values, $selectedValues = null, array $options = []) {
        $checkboxes = [];
        if (\is_array($selectedValues) === false) {
            $selectedValues = [$selectedValues];
        }
        foreach ($values as $value => $description) {
            $checkboxes[] = self::tag(
                'label',
                self::checkboxInput("{$name}[]", $value, \in_array($value, $selectedValues), $options).$description,
                ['class' => 'checkbox-inline']
            );
        }
        return implode('', $checkboxes);
    }

    public static function warning($text, $html = true)
    {
        if ($html)
            $text = View::toHtml($text);
        return "<div class=\"alert alert-warning row m-0 p-1\"><div class='col-auto p-1'><i class=\"fa fa-fw fa-exclamation-circle\"></i></div><div class='col p-1'>{$text}</div></div>";
    }

    public static function error($text, $html = true)
    {
        if ($html)
            $text = View::toHtml($text);
        return "<div class=\"alert alert-danger row m-0 p-1\"><div class='col-auto p-1'><i class=\"fa fa-fw fa-exclamation-circle\"></i></div><div class='col p-1'>{$text}</div></div>";
    }

    public static function info($text, $html = true)
    {
        if ($html)
            $text = View::toHtml($text);
        return "<div class=\"alert alert-info row m-0 p-1\"><div class='col-auto p-1'><i class=\"fa fa-fw fa-info-circle\"></i></div><div class='col p-1'>{$text}</div></div>";
    }

    public static function openWindow($url, $wind, $msg)
    {
        $wind = addslashes($wind);
        $msg = addslashes($msg);
        return "
            <script>
                var $wind = window.open('$url');
                if ($wind == null || typeof $wind == 'undefined')
                    alert('$msg');
                else
                    $wind.focus();
            </script>
        ";
    }

    public static function closeWindow($wind = null): string
    {
        return '<script>' . (empty($wind) ? 'window' : $wind) . '.close();</script>';
    }


    public static function alert($text): string
    {
        return "<script>alert('{$text}');</script>";
    }

    public static function widget($item, $values, $icon, $color = 'primary')
    {
        $lines = [];
        foreach ($values as $value)
            $lines[] = "
                <tr>
                    <td class='p-1'>{$value['name']}</td>
                    <td class='p-1" . (!$value['qty'] ? ' bg-danger text-white' : '') .  "' width='15%'>{$value['qty']}</td>
                </tr>";
        $lines = '<table class="table table-hover mb-0">' . implode("\n", $lines) . '</table>';
        return self::div(
            self::div(
                self::div(
                    self::div(
                        self::div(
                            self::div($item, ['class' => "font-weight-bold text-$color text-uppercase mb-1"]) .
                            self::div($lines, ['class' => 'h5 mb-0 text-xs font-weight-bold']),
                            ['class' => 'col']
                        ) .
                        self::div(
                            "<i class=\"fas fa-$icon fa-3x text-gray-300\"></i>",
                            ['class' => 'col-auto ml-3']
                        ), ['class' => 'row no-gutters align-items-top']
                    ), ['class' => 'card-body']
                ), ['class' => "card border-left-$color shadow h-100"]
            ), ['class' => 'col-xl-3 col-md-6 col-6 col-sm-6 mb-4']
        );
    }

    /** $name_id:
     * null  - name => $name
     * false -                id => $name
     * true  - name => $name, id => $name
     * else  - name => $name, id => $name_id
     */
    public static function hidden($name, $value, $name_id = null)
    {
        return
            Html::tag('input', null, [
                    'type' => 'hidden',
                    'value' => $value
                ] + (($name_id !== false ? ['name' => $name] : []) +
                    (is_null($name_id) ? [] : ['id' => (is_bool($name_id) ? $name : $name_id)])));
    }

    public static function hiddens(array $elements)
    {
        return implode(array_map(function($item) {
            if (empty($item[0]))
                return '';
            return self::hidden(...$item);
        }, $elements));
    }

    public static function button($text, $type = self::TYPE_BUTTON__PRIMARY, $options = [])
    {
        if (empty($options['class']))
            $options['class'] = [];
        if (!is_array($options['class']))
            $options['class'] = explode(' ', $options['class']);
        switch ($type) {
            case self::TYPE_BUTTON__PRIMARY:
                $options['class'] = array_merge($options['class'], ['btn', 'btn-orange']);
                break;
            case self::TYPE_BUTTON__CANCEL:
                $options['class'] = array_merge($options['class'], ['btn', 'btn-outline-orange']);
                break;
        }
        $options['class'] = implode(' ', $options['class']);

        return self::tag('button', $text, $options);
    }

    public static function multiSelect($name, $select_options = [], $selected_value = null, $options = [])
    {
        if (!is_array($selected_value)) {
            $selected_value = explode(',', $selected_value);
        }
        $defaultOptions = [
            'class' => 'form-control form-control-lg multi-select',
            'data-allow-duplicates' => false,
            'data-regexp' => null,
            'data-options' => json_encode($select_options),
            'data-selected' => json_encode($selected_value),
        ];
        if (isset($options['class']))
            $options['class'] = (is_array($options['class']) ? implode(' ', $options['class']) : $options['class']) . ' multi-select';

        $options = array_merge($defaultOptions, $options);

        // Handle selected values
        if (is_string($selected_value)) {
            $selected_value = explode(',', $selected_value);
        }
        if (!is_array($selected_value)) {
            $selected_value = [];
        }

        // Use dropDownList for select element
        return self::dropDownList($name, $select_options, [], $options);
    }

    public static function span($name, $options = [])
    {
        return self::tag('span', $name, $options);
    }
}
