<?
function smarty_modifier_date($value,$format) {
	return date($format,strtotime($value));
}?>
