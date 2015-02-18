<?php
/**
 * 正負の実数 (0-9, -, .)
 *
 * 不正確にならないよう、浮動小数点数型にはキャストしません。<br>
 * そのため、"12.3-45-6"といった表現が返されることがあります。<br>
 * 浮動小数点数として利用する場合は適切な型にキャストして下さい。<br>
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	$value = preg_replace('/[^-0-9\.]/', '', $value);

	return $value;
};
