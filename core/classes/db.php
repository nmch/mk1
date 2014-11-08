<?php

/**
 * Class DB
 *
 * @method static Database_Query select()
 * @method static Database_Query insert()
 * @method static Database_Query delete()
 * @method static Database_Query update()
 */
class DB
{
	private $connections = [];

	static function query($query, $parameters = [])
	{
		return new Database_Query($query, $parameters);
	}

	/**
	 * @param $name
	 * @param $args
	 *
	 * @see Database_Query
	 * @return mixed
	 * @throws MkException
	 */
	static function __callStatic($name, $args)
	{
		$query = new Database_Query();
		if( ! method_exists($query, $name) ){
			throw new MkException("method $name not found");
		}

		return call_user_func_array([$query, $name], $args);
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

	static function array_to_pgarraystr($data, $delimiter = ',', $typecat = 'S')
	{
		foreach($data as $key => $value){
			if( is_array($value) ){
				$data[$key] = static::array_to_pgarraystr($value, $delimiter);
			}
		}
		if( $typecat == 'S' ){
			$data = array_map(function ($str) {
					return '"' . pg_escape_string($str) . '"';
				}, $data
			);
		}
		else{
			$data = array_map(function ($str) {
					return is_numeric($str) ? $str : 'NULL';
				}, $data
			);
		}
		$str = '{' . implode($delimiter, $data) . '}';

		return $str;
	}

	/**
	 * データベース接続を取得する
	 *
	 * @param string|Database_Connection|null $connection
	 *
	 * @return Database_Connection
	 * @throws MkException
	 */
	static function get_database_connection($connection = NULL)
	{
		if( ! is_object($connection) ){
			$connection = Database_Connection::instance($connection);
		}
		if( ! $connection instanceof Database_Connection ){
			throw new MkException('invalid connection');
		}

		return $connection;
	}

	static function copy_from($table_name, $rows, $connection = NULL, $delimiter = "\t", $null_as = '')
	{
		//Log::coredebug($table_name,$rows);
		$dbconn = static::get_database_connection($connection);

		return $dbconn->copy_from($table_name, $rows, $delimiter, $null_as);
	}

	/**
	 * DBスキーマのキャッシュを消去する
	 */
	static function clear_schema_cache()
	{
		Database_Schema::clear_cache();
	}

	/**
	 * スキーマに存在する全テーブルを削除する
	 */
	static function delete_all_tables($schema = 'public')
	{
		DB::query("drop schema $schema cascade")->execute();
		DB::query("create schema $schema")->execute();
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
