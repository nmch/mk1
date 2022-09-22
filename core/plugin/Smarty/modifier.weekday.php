<?php

function smarty_modifier_weekday($value)
{
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

    return Arr::get($weekdays, $value);
}
