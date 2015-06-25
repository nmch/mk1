<?php
/**
 * intval()
 *
 * @return integer
 */
return function ($value) {
	return strlen($value) ? intval($value) : null;
};
