<?php

return function ($value) {
    if (strlen($value)) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidateErrorException('正しいメールアドレスの形式ではありません');
        }
    }
};
