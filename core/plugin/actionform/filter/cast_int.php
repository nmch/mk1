<?php

/**
 * integerへキャスト
 *
 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
 *
 * @return integer
 */
return function ($value) {
    if (!is_scalar($value)) {
        return null;
    }

    $value = (int) $value;

    return $value;
};
