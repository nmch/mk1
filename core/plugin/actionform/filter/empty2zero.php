<?php
/**
 * empty2zero
 *
 * empty()が真と判定した値を0に変換する。
 *
 * @return string|int
 */
return function ($value) {
	return empty($value) ? 0 : $value;
};
