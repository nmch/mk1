<?php
function smarty_modifier_empty_or_number_format($value)
{
	return strlen($value) ? number_format($value) : '';
}