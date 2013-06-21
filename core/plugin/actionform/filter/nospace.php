<?
/**
 * スペースを削除
 * 
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 * 
 * @return string
 */
return function($value)
{
	if( ! is_scalar($value) )
		return NULL;
	
	$value = str_replace(' ','',$value);
	return $value;
};
