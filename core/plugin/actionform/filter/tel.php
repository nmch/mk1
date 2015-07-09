<?php
/**
 * 国内の電話番号パターンを切り出し (012-3456-7890)
 *
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return string
 */
return function ($value) {
	if( ! is_scalar($value) ){
		return null;
	}

	if( preg_match('/([0-9]+-[0-9]+-[0-9]+)/', $value, $match) ){
		return $match[1];
	}
	else{
		return null;
	}
	//$value = preg_replace('/[^0-9-]/','',$value);
	//return $value;
};
