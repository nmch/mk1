<?
function smarty_modifier_addstr($str,$before,$after = "",$force = 0) {
	
	if(strlen($str) || $force)
		$str = "$before$str$after";
	
	return $str;
}?>
