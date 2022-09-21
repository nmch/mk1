<?php

function smarty_modifier_convert_kanahan($string)
{
    $string = mb_convert_kana($string, 'k');

    return $string;
}
