<?
/**
 * ASCIIのみ (0x00 - 0x7F)
 *
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	$value = preg_replace('/[^\x00-\x7F]/', '', $value);

	return $value;
};
