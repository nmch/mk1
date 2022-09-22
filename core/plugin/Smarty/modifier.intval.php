<?php

function smarty_modifier_intval($value)
{
    return strlen($value) ? intval($value) : null;
}
