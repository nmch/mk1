<?php

/**
 * "true"/"false"をbooleanとして判定
 *
 * @return null|bool
 */
return function ($value) {
    return strlen($value) ? ($value == '1' || strtolower($value) === 'true') : null;
};
