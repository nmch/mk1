<?php

/**
 * 日付(YYYY-MM-DDまたはYYYY/MM/DD形式)
 */
return function ($value) {
    if (!$value) {
        return $value;
    }
    if (preg_match('#^([0-9]+)[-/]([0-9]+)[-/]([0-9]+)$#', $value, $match)) {
        if (checkdate($match[2], $match[3], $match[1])) {
            return $value;
        }
    }
    throw new ValidateErrorException('正しい日付の形式ではありません');
};
