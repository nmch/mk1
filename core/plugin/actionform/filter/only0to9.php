<?
/**
 * 正の整数 (0-9)
 * 
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 * 
 * @return integer
 */
return function($value)
{
	if( ! is_scalar($value) )
		return NULL;
	
	$value = preg_replace('/[^0-9]/','',$value);
	if(strlen($value) == 0)
		$value = NULL;
	else
		$value = (int)$value;
	return (int)$value;
};
