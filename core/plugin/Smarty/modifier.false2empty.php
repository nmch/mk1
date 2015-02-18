<?php
/**
 * false判定される値の場合は空文字列に変換する
 */
function smarty_modifier_false2empty($str)
{
	return $str ?: '';
} ?>
