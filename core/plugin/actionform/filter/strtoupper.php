<?php
/**
 * 大文字変換
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	return strtoupper($value);
};
