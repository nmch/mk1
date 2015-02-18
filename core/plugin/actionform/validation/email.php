<?php
return function ($value) {
	if( $value ){
		if( ! preg_match('/^[^@]+@.+\..+/', $value) ){
			throw new ValidateErrorException('形式が違います');
		}
	}
};
