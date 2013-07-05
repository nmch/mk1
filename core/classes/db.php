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
	static function expr($expr)
	{
		return new Database_Expression($expr);
	}
	static function array_to_pgarraystr($data,$delimiter = ',')
	{
		foreach($data as $key => $value){
			if(is_array($value))
				$data[$key] = static::array_to_pgarraystr($value,$delimiter);
		}
		$data = array_map(function($str){ return '"'.addslashes($str).'"'; }, $data);
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
