<?php
/**
 * 小文字変換
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	return strtolower($value);
};
