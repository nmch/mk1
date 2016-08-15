<?php
function smarty_modifier_mb_truncate($string, $length = 80, $etc = '...')
{
	$string_length = mb_strlen($string);
	if( ! $string_length ){
		return '';
	}
	
	//return mb_strimwidth($string, 0, $length, $etc);
	$return_str = mb_substr($string, 0, $length);
	if( $string_length > $length ){
		$return_str .= $etc;
	}
	
	return $return_str;
}
