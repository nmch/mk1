<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Database_Type
{
	protected static $type;
	protected static $type_hashed_by_oid;
	
	static function get($name = null, $default = null, $connection = null)
	{
		static::make_cache($connection);
		
		return $name ? Arr::get(static::$type, $name, $default) : static::$type;
	}
	
	static function get_by_oid($oid = null, $default = null, $connection = null)
	{
		static::make_cache($connection);
		
		return $oid ? Arr::get(static::$type_hashed_by_oid, $oid, $default) : static::$type_hashed_by_oid;
	}
	
	static function make_cache($connection = null)
	{
		if( ! static::$type || ! static::$type_hashed_by_oid ){
			// キャッシュからの読み込みを試す
			static::$type = Cache::get('type', 'core_db');
			if( ! static::$type ){
				static::$type = static::retrieve($connection);
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
	
	static function retrieve($connection = null)
	{
		if( ! $connection ){
			$connection = Database_Connection::instance();
		}
		
		$type_list = [];
		$result    = $connection->query('SELECT oid,* FROM pg_type', null, false, true);
		while($row = pg_fetch_assoc($result)){
			$type_list[$row['typname']] = $row;
		}
		unset($r);
		
		return $type_list;
	}
}
