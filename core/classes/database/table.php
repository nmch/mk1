<?php

/**
 * DB テーブル
 */
class Database_Table
{
	protected static $tables;
	protected static $tables_hashed_by_oid;
	
	static function get($name = null, $default = null)
	{
		static::make_cache();
		
		return $name ? Arr::get(static::$tables, $name, $default) : static::$tables;
	}
	
	static function get_by_oid($oid = null, $default = null)
	{
		static::make_cache();
		
		return $oid ? Arr::get(static::$tables_hashed_by_oid, $oid, $default) : static::$tables_hashed_by_oid;
	}
	
	static function make_cache()
	{
		if( ! static::$tables || ! static::$tables_hashed_by_oid ){
			// キャッシュからの読み込みを試す
			static::$tables = Cache::get('table', 'core_db');
			if( ! static::$tables ){
				static::$tables = static::retrieve();
				Cache::set('table', 'core_db', static::$tables);
			}
			
			static::$tables_hashed_by_oid = Cache::get('tables_hashed_by_oid', 'core_db');
			if( ! static::$tables_hashed_by_oid ){
				foreach(static::$tables as $table){
					static::$tables_hashed_by_oid[$table['oid']] = $table;
				}
				Cache::set('tables_hashed_by_oid', 'core_db', static::$tables_hashed_by_oid);
			}
		}
	}
	
	static function retrieve()
	{
		$table_list = [];
		$result     = Database_Connection::instance()->query('SELECT oid,* FROM pg_class', null, false, true);
		while ($row = pg_fetch_assoc($result)) {
			$table_list[$row['relname']] = $row;
		}
		unset($r);
		
		return $table_list;
	}
}
