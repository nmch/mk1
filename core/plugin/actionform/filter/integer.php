<?
/**
 * 正負の整数 (0-9, -)
 * 
 * "12.34"は1234になります。<br>
 * integerにキャストされるので、"-012a-345"は-12になります。<br>
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 * 
 * @param string
 * @return integer
 */
return function($value)
{
	if( ! is_scalar($value) )
		return NULL;
	
	$value = preg_replace('/[^-0-9]/','',$value);
	if(strlen($value) == 0)
		$value = NULL;
	else
		$value = (int)$value;
	return $value;
};
