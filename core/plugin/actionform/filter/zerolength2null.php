<?php
/**
 * zerolength2null
 *
 * strlen()の結果が0の場合NULLに変換する。
 *
 * @return string
 */
return function ($value) {
	return strlen($value) === 0 ? null : $value;
};
