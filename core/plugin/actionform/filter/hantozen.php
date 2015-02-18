<?php
/**
 * 半角カナを全角へ変換
 *
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	return mb_convert_kana($value, 'KV');
};
