<?
/**
 * 日付(YYYY-MM-DD)
 * 
 * strtotime()を通すので相対形式も使えます
 * 
 * @return string
 */
return function($value)
{
	return date('Y-m-d',strtotime($value));
};