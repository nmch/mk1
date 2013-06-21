<?
function smarty_modifier_quote($array) {
	if(!is_array($array))
		$array = array();
	foreach($array as $key => $value)
		$array[$key] = "'$value'";
	return $array;
}?>
