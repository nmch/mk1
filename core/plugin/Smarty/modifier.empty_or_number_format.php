<?php
function smarty_modifier_empty_or_number_format($value, $decimals = 0)
{
	return strlen($value) ? number_format($value, $decimals) : '';
}