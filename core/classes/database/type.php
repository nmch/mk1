<?
class Database_Type
{
	protected static $type;
	
	static function get($name = NULL,$default = NULL)
	{
		if( ! static::$type )
			static::$type = static::retrieve();
		
		return $name ? Arr::get(static::$type,$name,$default) : static::$type;
	}
	
	static function retrieve()
	{
		$type_list = array();
		$r = DB::select()->from('pg_type')->execute()->as_array();
		foreach($r as $type){
			$type_list[$type['typname']] = $type;
		}
		return $type_list;
	}
}
