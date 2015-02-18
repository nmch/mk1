<?
/**
 * アルファベットのみ(A-Z, a-z)
 *
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	$value = preg_replace('/[^a-zA-Z]/', '', $value);

	return $value;
};
