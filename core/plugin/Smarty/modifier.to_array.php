<?
function smarty_modifier_to_array($value) {
	return is_array($value) ? $value : array($value);
}?>
