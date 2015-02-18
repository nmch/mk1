<?
/**
 * emailでよく間違える文字を正しく変換
 *
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	$value = preg_replace(
		[
			'/,/'
		],
		[
			'.'
		]
		, $value
	);

	return $value;
};
