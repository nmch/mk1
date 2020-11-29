<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Database_Schema
{
	protected static $_attributes = [];
	protected static $schema;
	
	static function get($name = null, $default = null, $no_cache = false, $database = null)
	{
		$connection    = Database_Connection::instance($database);
		$database_name = $connection->get_current_database_name();
		
		$cache_key = "schema_{$database_name}";
		
		if( empty(static::$schema[$database_name]) && ! $no_cache ){
			// キャッシュからの読み込みを試す
			static::$schema[$database_name] = Cache::get($cache_key, 'core_db');
		}
		if( $no_cache ){
			static::$schema[$database_name] = null;
		}
		if( empty(static::$schema[$database_name]) ){
			static::$schema[$database_name] = static::retrieve($database);
			Cache::set($cache_key, 'core_db', static::$schema[$database_name]);
		}
		
		if( $name ){
			$retval = Arr::get(static::$schema[$database_name], "public.{$name}", $default);
		}
		else{
			$retval = static::$schema[$database_name]['public'];
		}
		
		return $retval;
	}
	
	/**
	 * @return array
	 * @throws DatabaseQueryError
	 * @throws MkException
	 */
	static function retrieve($database = null)
	{
		$connection = Database_Connection::instance($database);
		
		$primary_keys = [];
		$constrains   = DB::select("conrelid,conkey")->from('pg_constraint')->where('contype', 'p')->execute($connection);
		foreach($constrains as $const){
			$primary_keys[$const['conrelid']] = $const['conkey'];
		}
		
		$q                   = <<<SQL
SELECT
	c.oid AS table_oid,
	nspname AS schema,
	relname AS table,
	attname AS name,
	attnum AS num,
	attndims AS dims,
	attnotnull AS not_null,
	typname AS type,
	typlen AS len,
	typcategory AS type_cat,
	description AS desc
FROM pg_class AS c
JOIN pg_namespace			n ON n.oid = c.relnamespace
JOIN pg_attribute			a ON a.attrelid = c.oid
JOIN pg_type				t ON t.oid = a.atttypid
LEFT JOIN pg_description	d ON objoid = c.oid AND objsubid=a.attnum

WHERE c.relkind='r'
AND n.nspname='public'
AND attnum >= 0 AND attisdropped IS NOT TRUE
ORDER BY relname,attnum
SQL;
		$attributes          = DB::query($q)->execute($connection);
		static::$_attributes = $attributes;
		
		$tables = [];
		foreach($attributes as $attr){
			$schema = $attr['schema'];
			
			if( empty($tables[$schema][$attr['table']]) ){
				$tables[$schema][$attr['table']] = [
					'name'        => $attr['table'],
					'description' => $attr['desc'],
					'columns'     => [],
					'primary_key' => [],
				];
			}
			$tables[$schema][$attr['table']]['columns'][$attr['name']] = $attr;
			if( isset($primary_keys[$attr['table_oid']]) && in_array($attr['num'], $primary_keys[$attr['table_oid']]) ){
				$tables[$schema][$attr['table']]['columns'][$attr['name']]['primary_key'] = true;
				$tables[$schema][$attr['table']]['primary_key'][]                         = $attr['name'];
				$tables[$schema][$attr['table']]['has_pkey']                              = true;
			}
		}
		
		return $tables;
	}
	
	/**
	 * DBスキーマのキャッシュを消去する
	 */
	static function clear_cache()
	{
		static::$schema = null;
		
		$cache_dir = Cache::cache_dir(null, 'core_db');
		Log::coredebug("Database_Schema::clear_cache() cache_dir=$cache_dir", Mk::env());
		if( is_dir($cache_dir) ){
			File::rm($cache_dir);
			Log::coredebug("cache clear success");
		}
	}
}
