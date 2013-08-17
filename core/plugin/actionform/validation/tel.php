<?
/**
 * 電話番号(日本)
 */
return function($value)
{
	if( strlen($value) > 0 && ! preg_match('/^0[0-9]+-[-0-9]+[0-9]$/',$value) )
		throw new ValidateErrorException('形式が違います');
};
