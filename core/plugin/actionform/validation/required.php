<?php

return function ($value) {
    if (!isset($value) || !strlen($value)) {
        throw new ValidateErrorException('必須項目です');
    }
};
