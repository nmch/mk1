<?php
return function ($value, $op) {
	$min = Arr::get($op, 0);
	$max = Arr::get($op, 1);

	$length = strlen($value);
	if( ($min && $length < $min) ||
		($max && $max < $length)
	){
		throw new ValidateErrorException('長さが足りません');
	}
};
