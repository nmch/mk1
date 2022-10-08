<?php

/**
 * 全角の英数字とスペースを半角変換
 *
 * @return string
 */
return function ($value) {
    $new_value = mb_convert_kana($value ?? '', "sa");

    return $new_value;
};
