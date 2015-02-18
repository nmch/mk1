<?php

class Database_Schema
{
	protected static $_attributes = [];
	protected static $schema;

	static function get($name = null, $default = null)
	{
		if( ! static::$schema ){
			// キャッシュからの読み込みを試す
			static::$schema = Cache::get('schema', 'core_db');
			if( ! static::$schema ){
				static::$schema = static::retrieve();
				Cache::set('schema', 'core_db', static::$schema);
			}
		}

		return $name ? Arr::get(static::$schema, $name, $default) : static::$schema;
	}

	/**
	 * @return array
	 * @throws DatabaseQueryError
	 * @throws MkException
	 */
	static function retrieve()
	{
		$primary_keys = [];
		$constrains   = DB::select("conrelid,conkey")->from('pg_constraint')->where('contype', 'p')->execute();
		foreach($constrains as $const){
			$primary_keys[$const['conrelid']] = $const['conkey'];
		}

		$q                   = "
			SELECT
				c.oid AS table_oid,
				relname AS table,
				relhaspkey AS table_has_pkey,
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
			ORDER BY attnum
		";
		$attributes          = DB::query($q)->execute();
		static::$_attributes = $attributes;

		$tables = [];
		foreach($attributes as $attr){
			if( empty($tables[$attr['table']]) ){
				$tables[$attr['table']] = [
					'name'        => $attr['table'],
					'has_pkey'    => $attr['table_has_pkey'],
					'description' => $attr['desc'],
					'columns'     => [],
					'primary_key' => [],
				];
			}
			$tables[$attr['table']]['columns'][$attr['name']] = $attr;
			if( isset($primary_keys[$attr['table_oid']]) && in_array($attr['num'], $primary_keys[$attr['table_oid']]) ){
				$tables[$attr['table']]['columns'][$attr['name']]['primary_key'] = true;
				$tables[$attr['table']]['primary_key'][]                         = $attr['name'];
				//Log::coredebug("find primary key {$attr['name']}");
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
