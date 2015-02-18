<?php

/**
 * DB データ型
 */
class Database_Type
{
	protected static $type;
	protected static $type_hashed_by_oid;

	static function get($name = null, $default = null)
	{
		static::make_cache();

		return $name ? Arr::get(static::$type, $name, $default) : static::$type;
	}

	static function make_cache()
	{
		if( ! static::$type || ! static::$type_hashed_by_oid ){
			// キャッシュからの読み込みを試す
			static::$type = Cache::get('type', 'core_db');
			if( ! static::$type ){
				static::$type = static::retrieve();
				Cache::set('type', 'core_db', static::$type);
			}

			static::$type_hashed_by_oid = Cache::get('type_hashed_by_oid', 'core_db');
			if( ! static::$type_hashed_by_oid ){
				foreach(static::$type as $type){
					static::$type_hashed_by_oid[$type['oid']] = $type;
				}
				Cache::set('type_hashed_by_oid', 'core_db', static::$type_hashed_by_oid);
			}
		}
	}

	static function retrieve()
	{
		$type_list = [];
		$r         = DB::select('oid,*')->from('pg_type')->execute()->as_array();
		foreach($r as $type){
			$type_list[$type['typname']] = $type;
		}
		unset($r);

		return $type_list;
	}

	static function get_by_oid($oid = null, $default = null)
	{
		static::make_cache();

		return $oid ? Arr::get(static::$type_hashed_by_oid, $oid, $default) : static::$type_hashed_by_oid;
	}
}
