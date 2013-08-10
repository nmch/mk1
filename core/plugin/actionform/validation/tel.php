<?
/**
 * 電話番号(日本)
 */
return function($value)
{
	if( ! preg_match('/^0[0-9]+-[-0-9]+[0-9]$/',$value) )
		throw new ValidateErrorException('形式が違います');
};
