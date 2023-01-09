<?php

return function ($value, $op) {
    $min = Arr::get($op, 0);
    $max = Arr::get($op, 1);

    $length = mb_strlen($value);
    if (($min && $length < $min)) {
        throw new ValidateErrorException("最低{$min}文字が必要です");
    }
    if (($max && $max < $length)) {
        throw new ValidateErrorException("最大{$max}文字までです");
    }
};
