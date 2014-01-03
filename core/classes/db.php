<?
class DB
{
	private $connections = array();
	
	static function query($query,$parameters = array())
	{
		return new Database_Query($query,$parameters);
	}
	static function __callStatic($name,$args)
	{
		$query = new Database_Query();
		if( ! method_exists($query,$name) )
			throw new MkException("method $name not found");
		return call_user_func_array(array($query,$name),$args);
	}
	static function escape($value)
	{
		return static::escape_literal($value);
	}
	static function escape_literal($value)
	{
		$dbconn = static::get_database_connection();
		return $dbconn->escape_literal($value);
	}
	static function expr($expr)
	{
		return new Database_Expression($expr);
	}
	static function array_to_pgarraystr($data,$delimiter = ',',$typecat = 'S')
	{
		foreach($data as $key => $value){
			if(is_array($value))
				$data[$key] = static::array_to_pgarraystr($value,$delimiter);
		}
		if($typecat == 'S')
			$data = array_map(function($str){ return '"'.pg_escape_string($str).'"'; }, $data);
		else
			$data = array_map(function($str){ return is_numeric($str) ? $str : 'NULL'; }, $data);
		$str = '{'.implode($delimiter,$data).'}';
		return $str;
	}
	
	static function get_database_connection($connection = NULL)
	{
		if( ! is_object($connection) )
			$connection = Database_Connection::instance($connection);
		if( ! $connection instanceof Database_Connection )
			throw new MkException('invalid connection');
		
		return $connection;
	}
	
	static function copy_from($table_name , $rows, $connection = NULL)
	{
		//Log::coredebug($table_name,$rows);
		$dbconn = static::get_database_connection($connection);
		return $dbconn->copy_from($table_name , $rows);
	}
	
	/**
	 * DBスキーマのキャッシュを消去する
	 */
	static function clear_schema_cache()
	{
		Database_Schema::clear_cache();
	}
	
	/**
	 * publicスキーマに存在する全テーブルを削除する
	 */
	static function delete_all_tables()
	{
		Database_Schema::clear_cache();
		$schema = Database_Schema::get();
		DB::query("drop table ".implode(',',array_keys($schema))." CASCADE")->execute();
		Database_Schema::clear_cache();
	}
	
	static function pager($db_query, $options)
	{
		return new Database_Pager($db_query, $options);
	}
	static function in_transaction($connection = NULL)
	{
		return static::get_database_connection($connection)->in_transaction();
	}
	static function start_transaction($connection = NULL)
	{
		return static::get_database_connection($connection)->start_transaction();
	}
	static function commit_transaction($connection = NULL)
	{
		return static::get_database_connection($connection)->commit_transaction();
	}
	static function rollback_transaction($connection = NULL)
	{
		return static::get_database_connection($connection)->rollback_transaction();
	}
	static function place_savepoint($connection = NULL)
	{
		return static::get_database_connection($connection)->place_savepoint();
	}
	static function commit_savepoint($point_name, $connection = NULL)
	{
		return static::get_database_connection($connection)->commit_savepoint($point_name);
	}
	static function rollback_savepoint($point_name, $connection = NULL)
	{
		return static::get_database_connection($connection)->rollback_savepoint($point_name);
	}
}
