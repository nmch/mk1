<?
/**
 * empty2null
 *
 * empty()が真と判定した値をNULLに変換する。
 *
 * @return string
 */
return function ($value) {
	return empty($value) ? null : $value;
};
