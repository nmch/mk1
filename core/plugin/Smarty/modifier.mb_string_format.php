<?php

function smarty_modifier_mb_string_format($string, $format)
{
    $r = sprintf($format, $string);
    if (mb_strlen($string) > $length) {
        return mb_substr($string, 0, $length).$etc;
    } else {
        return $string;
    }
}
