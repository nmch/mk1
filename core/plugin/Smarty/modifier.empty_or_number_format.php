<?php

function smarty_modifier_empty_or_number_format($value, $decimals = 0, $dec_point = '.', $thousands_sep = ',')
{
    return strlen($value ?? '') ? number_format($value, $decimals, $dec_point, $thousands_sep) : '';
}
