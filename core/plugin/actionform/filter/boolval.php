<?php

/**
 * boolval()
 *
 * @return null|bool
 */
return function ($value) {
    return strlen($value) ? boolval($value) : null;
};
