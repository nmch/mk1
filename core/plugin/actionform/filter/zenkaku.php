<?
/**
 * 半角の英数字とスペースを全角変換
 * @return string
 */
return function($value)
{
	$new_value = mb_convert_kana($value,"KVSA");
	return $new_value;
};
