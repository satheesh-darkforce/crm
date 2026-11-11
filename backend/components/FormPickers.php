<?php

namespace backend\components;

use kartik\date\DatePicker;
use kartik\select2\Select2;
use kartik\time\TimePicker;

/**
 * Shared widget settings so every form uses the same pickers:
 *
 *   $form->field($m, 'visit_date')->widget(DatePicker::class, FormPickers::date())
 *   $form->field($m, 'visit_time')->widget(TimePicker::class, FormPickers::time())
 *   $form->field($m, 'owner')->widget(Select2::class, ['data' => $users] + FormPickers::select('Pick someone'))
 *
 * The look comes from web/css/gd-theme.css.
 */
class FormPickers
{
    /** kartik DatePicker, plain input + calendar icon, yyyy-mm-dd. */
    public static function date(array $plugin = [], string $placeholder = 'YYYY-MM-DD'): array
    {
        return [
            'type'          => DatePicker::TYPE_INPUT,
            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder, 'autocomplete' => 'off'],
            'pluginOptions' => $plugin + ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
        ];
    }

    /**
     * kartik TimePicker, 24-hour HH:MM in 5-minute steps. Stays empty until the
     * user picks a time (defaultTime false), so optional times are not filled in.
     */
    public static function time(array $plugin = [], string $placeholder = 'HH:MM'): array
    {
        return [
            'size'          => 'sm',
            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder, 'autocomplete' => 'off', 'inputmode' => 'numeric'],
            'addonOptions'  => ['class' => 'gd-picker-addon'],
            'pluginOptions' => $plugin + [
                'showMeridian' => false,
                'showSeconds'  => false,
                'minuteStep'   => 5,
                'defaultTime'  => false,
                'explicitMode' => true,
            ],
        ];
    }

    /** kartik Select2 with clear button, full width, small size. */
    public static function select(string $placeholder, array $options = [], array $plugin = []): array
    {
        return [
            'options'       => ['placeholder' => $placeholder] + $options,
            'pluginOptions' => $plugin + ['allowClear' => true, 'width' => '100%'],
            'size'          => Select2::SMALL,
        ];
    }
}
