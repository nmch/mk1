<?php
/**
 * 複数行の正負の整数 (0-9, -, \n)
 *
 * \nで分割したあと、それぞれに対してintegerフィルタを適用し<br>
 * 再度\nで連結したものを返します
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}
	$integer = include './integer.php';

	$integers = explode("\n", $value);
	$integers = array_map($integer, $integers);

	return implode("\n", $integers);
};
