<?php

function smarty_modifier_isodow_weekday($value)
{
    $weekdays = ['', '月', '火', '水', '木', '金', '土', '日'];

    return Arr::get($weekdays, $value);
}
