<?php

/**
 * 電話番号(日本)
 */
return function ($value) {
    if (strlen($value) > 0 && !preg_match('/^0[0-9]+-?[0-9]+-?[0-9]+$/', $value)) {
        throw new ValidateErrorException("市外局番から0NN-NNN-NNNNの形式で入力して下さい(例:03-1234-5678)");
    }
};
