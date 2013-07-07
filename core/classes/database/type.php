<?
class Database_Type
{
	protected static $type;
	
	static function get($name = NULL,$default = NULL)
	{
		if( ! static::$type ){
			// キャッシュからの読み込みを試す
			static::$type = Cache::get('type','core_db');
			if( ! static::$type ){
				static::$type = static::retrieve();
				Cache::set('type','core_db',static::$type);
			}
		}
		
		return $name ? Arr::get(static::$type,$name,$default) : static::$type;
	}
	
	static function retrieve()
	{
		$type_list = array();
		$r = DB::select()->from('pg_type')->execute()->as_array();
		foreach($r as $type){
			$type_list[$type['typname']] = $type;
		}
		unset($r);
		return $type_list;
	}
}
