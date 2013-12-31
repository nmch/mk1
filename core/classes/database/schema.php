<?
class Database_Schema
{
	protected static $_attributes = array();
	protected static $schema;
	
	static function get($name = NULL,$default = NULL)
	{
		if( ! static::$schema ){
			// キャッシュからの読み込みを試す
			static::$schema = Cache::get('schema','core_db');
			if( ! static::$schema ){
				static::$schema = static::retrieve();
				Cache::set('schema','core_db',static::$schema);
			}
		}
		
		return $name ? Arr::get(static::$schema,$name,$default) : static::$schema;
	}
	
	/**
	 * DBスキーマのキャッシュを消去する
	 */
	static function clear_cache()
	{
		static::$schema = NULL;
		
		$cache_dir = Cache::cache_dir(NULL,'core_db');
		if(is_dir($cache_dir))
			File::rm($cache_dir);
	}
	
	static function retrieve()
	{
		$primary_keys = array();
		$constrains = DB::select("conrelid,conkey")->from('pg_constraint')->where('contype','p')->execute();
		foreach($constrains as $const){
			$primary_keys[$const['conrelid']] = $const['conkey'];
		}
		
		$q = "
			select
				c.oid as table_oid,
				relname as table,
				relhaspkey as table_has_pkey,
				attname as name,
				attnum as num,
				attndims as dims,
				attnotnull as not_null,
				typname as type,
				typlen as len,
				typcategory as type_cat,
				description as desc
			FROM PG_CLASS as c
			JOIN pg_namespace			n ON n.oid = c.relnamespace
			JOIN PG_ATTRIBUTE			a ON a.ATTRELID = c.OID 
			JOIN PG_TYPE				t ON t.OID = a.ATTTYPID 
			LEFT JOIN pg_description	d ON objoid = c.oid and objsubid=a.attnum
			
			where c.relkind='r'
			and n.nspname='public'
			and attnum >= 0 and attisdropped is not true
		";
		$attributes = DB::query($q)->execute();
		static::$_attributes = $attributes;
		
		$tables = array();
		foreach($attributes as $attr){
			if(empty($tables[$attr['table']])){
				$tables[$attr['table']] = array(
					'name' => $attr['table'],
					'has_pkey' => $attr['table_has_pkey'],
					'description' => $attr['desc'],
					'columns' => array(),
					'primary_key' => array(),
				);
			}
			$tables[$attr['table']]['columns'][$attr['name']] = $attr;
			if(isset($primary_keys[$attr['table_oid']]) && in_array($attr['num'],$primary_keys[$attr['table_oid']])){
				$tables[$attr['table']]['columns'][$attr['name']]['primary_key'] = true;
				$tables[$attr['table']]['primary_key'][] = $attr['name'];
				//Log::coredebug("find primary key {$attr['name']}");
			}
		}
		return $tables;
	}
}
