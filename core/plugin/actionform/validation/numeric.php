<?php

/**
 * 数値
 */
return function ($value, $options) {
    if (!is_numeric($value)) {
        throw new ValidateErrorException('正しい数値の形式ではありません');
    }

    $min = $options['min'] ?? null;
    $max = $options['max'] ?? null;
    if (strlen($min) || strlen($max)) {
        $error_message = "";

        if (strlen($min)) {
            if ($value < $min) {
                $error_message .= "{$min}以上";
            }
        }
        if (strlen($max)) {
            if ($max < $value) {
                $error_message .= "{$max}以下";
            }
        }
        if ($error_message) {
            $error_message .= "で入力してください";
            throw new ValidateErrorException($error_message);
        }
    }
};
