<?
/**
 * 小文字変換
 * 
 * @return string
 */
return function($value)
{
	if( ! is_scalar($value) )
		return NULL;
	
	return strtolower($value);
};
