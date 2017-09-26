<?php
/**
 * 日付(YYYY-MM-DD)
 *
 * strtotime()を通すので相対形式も使えます
 *
 * @return string
 */
return function ($value){
	$time = strtotime($value);
	
	return $time ? date('Y-m-d', $time) : null;
};
