<?php
function smarty_modifier_implode($array, $delimiter)
{
	return implode($delimiter, $array ?: []);
}