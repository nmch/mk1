<?php
/**
 * 選択肢
 */
return function ($value, $selection) {
	if( ! in_array($value, $selection) ){
		throw new ValidateErrorException('値が選択肢にありません');
	}
};
