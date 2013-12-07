<?
/**
 * URL
 */
return function($value)
{
	if($value){
		$parsed = parse_url($value);
		if(empty($parsed['scheme']) || empty($parsed['host']))
			throw new ValidateErrorException('形式が違います');
	}
};
