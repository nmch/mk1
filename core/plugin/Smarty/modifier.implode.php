<?php

function smarty_modifier_implode($array, $delimiter)
{
    if (!is_array($array)) {
        $array = [$array];
    }

    return implode($delimiter, $array ?: []);
}
