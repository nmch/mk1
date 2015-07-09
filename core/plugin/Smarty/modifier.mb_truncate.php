<?php
function smarty_modifier_mb_truncate($string, $length = 80, $etc = '...')
{
	if( $length == 0 ){
		return '';
	}

	return mb_strimwidth($string, 0, $length, $etc);
}
