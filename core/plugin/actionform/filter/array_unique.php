<?php
/**
 * array_unique
 *
 * valueにarray_unique()を適用する。配列ではない値を与えた場合はNULLを返す
 *
 * @return array
 */
return function ($value) {
	if( ! is_array($value) ){
		return null;
	}

	return array_unique($value);
};
