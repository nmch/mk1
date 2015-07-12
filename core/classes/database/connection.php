<?php

class Database_Connection
{
	/** @var Database_Connection[] $instances */
	static $instances = [];
	/** @var resource $connection */
	private $connection;
	private $savepoint_counter = 0;
	/** @var array 最後のエラー(最後のクエリが成功した場合は空配列) */
	private $last_error_details = [];

	function __construct($config)
	{
		if( empty($config['connection']) ){
			$config['connection'] = "";
		}

		$connection_config = "";
		if( is_array($config['connection']) ){
			foreach($config['connection'] as $key => $value){
				$connection_config .= "$key=$value ";
			}
		}
		else{
			$connection_config = $config['connection'];
		}

		//Log::coredebug("[db connection] try connect to $connection_config");
		$this->connection = pg_connect($connection_config);
		pg_set_client_encoding($this->connection, 'UTF-8');
		if( $this->connection === false ){
			throw new MkException('failed establish to db');
		}

		return $this;
	}

	/**
	 * @param string|null $name
	 *
	 * @return Database_Connection
	 */
	static function instance($name = null)
	{
		if( ! $name ){
			$name = Config::get('db.active');
		}
		//Log::coredebug("[db connection] try get a connection named $name");
		if( empty(static::$instances[$name]) ){
			$config                   = \Config::get("db.{$name}");
			static::$instances[$name] = new static($config);
		}

		return static::$instances[$name];
	}

	function dbname()
	{
		return pg_dbname($this->connection);
	}

	function escape_literal($value)
	{
		return pg_escape_literal($this->connection, $value);
	}

	function copy_from($table_name, $rows, $delimiter = "\t", $null_as = '')
	{
		return pg_copy_from($this->connection, $table_name, $rows, $delimiter, $null_as);
	}

	function query($sql, $parameters = [])
	{
		Log::coredebug("[dbconn] SQL {$this->connection} = $sql / " . var_export($parameters, true));

		// クエリ送信
		if( $parameters ){
			pg_send_query_params($this->connection, $sql, $parameters);
		}
		else{
			pg_send_query($this->connection, $sql);
		}

		// 結果を全て取得して、エラーがあれば例外スロー、なければ最後の結果を返す
		while( $r = pg_get_result($this->connection) ){
			if( $r !== false ){
				$query_result = $r;
				$error_msg    = trim(pg_result_error($query_result));
				if( $error_msg !== '' ){
					$error_details = [
						'message'                       => $error_msg
						, PGSQL_DIAG_SEVERITY           => pg_result_error_field($query_result, PGSQL_DIAG_SEVERITY)
						, PGSQL_DIAG_SQLSTATE           => pg_result_error_field($query_result, PGSQL_DIAG_SQLSTATE)
						, PGSQL_DIAG_MESSAGE_PRIMARY    => pg_result_error_field($query_result, PGSQL_DIAG_MESSAGE_PRIMARY)
						, PGSQL_DIAG_MESSAGE_DETAIL     => pg_result_error_field($query_result, PGSQL_DIAG_MESSAGE_DETAIL)
						, PGSQL_DIAG_MESSAGE_HINT       => pg_result_error_field($query_result, PGSQL_DIAG_MESSAGE_HINT)
						, PGSQL_DIAG_STATEMENT_POSITION => pg_result_error_field($query_result, PGSQL_DIAG_STATEMENT_POSITION)
						, PGSQL_DIAG_CONTEXT            => pg_result_error_field($query_result, PGSQL_DIAG_CONTEXT)
						, PGSQL_DIAG_SOURCE_FILE        => pg_result_error_field($query_result, PGSQL_DIAG_SOURCE_FILE)
						, PGSQL_DIAG_INTERNAL_POSITION  => pg_result_error_field($query_result, PGSQL_DIAG_INTERNAL_POSITION)
						, PGSQL_DIAG_INTERNAL_QUERY     => pg_result_error_field($query_result, PGSQL_DIAG_INTERNAL_QUERY)
						, PGSQL_DIAG_SOURCE_LINE        => pg_result_error_field($query_result, PGSQL_DIAG_SOURCE_LINE)
						, PGSQL_DIAG_SOURCE_FUNCTION    => pg_result_error_field($query_result, PGSQL_DIAG_SOURCE_FUNCTION)
					];
					Log::error("Query Error", $error_details);
					$this->last_error_details = $error_details;
					throw new DatabaseQueryError($error_msg);
				}
			}
		}
		$this->last_error_details = [];

		if( empty($query_result) ){
			throw new DatabaseQueryError("Result is empty");
		}

		return new Database_Resultset($query_result);
	}

	function rollback_transaction()
	{
		DB::query("ABORT")->execute($this);
	}

	/**
	 * セーブポイントを作成する
	 *
	 * @return string セーブポイント名
	 * @throws DatabaseQueryError
	 * @throws MkException
	 */
	function place_savepoint()
	{
		if( ! $this->in_transaction() ){
			$this->start_transaction();
		}

		$point_name = preg_replace('/[^0-9a-z]/', '', strtolower(uniqid(gethostname())));
		DB::query('SAVEPOINT ' . $point_name)->execute($this);
		Log::coredebug('[dbconn] SAVEPOINT ' . $point_name);
		$this->savepoint_counter++;

		return $point_name;
	}

	function in_transaction()
	{
		return ($this->get_transaction_status() != PGSQL_TRANSACTION_IDLE);
	}

	function get_transaction_status()
	{
		return pg_transaction_status($this->connection);
	}

	function start_transaction()
	{
		DB::query("BEGIN")->execute($this);
	}

	function commit_savepoint($point_name)
	{
		if( ! $this->in_transaction() ){
			throw new MkException('not in transaction');
		}
		if( ! $point_name ){
			throw new MkException('invalid point name');
		}

		DB::query('RELEASE SAVEPOINT ' . $point_name)->execute($this);
		$this->savepoint_counter--;
		if( $this->savepoint_counter < 1 ){
			$this->savepoint_counter = 0;
			$this->commit_transaction();
		}
	}

	function commit_transaction()
	{
		DB::query("COMMIT")->execute($this);
	}

	function rollback_savepoint($point_name)
	{
		if( ! $this->in_transaction() ){
			throw new MkException('not in transaction');
		}
		if( ! $point_name ){
			throw new MkException('invalid point name');
		}

		DB::query('ROLLBACK TO SAVEPOINT ' . $point_name)->execute($this);
		$this->savepoint_counter--;
		if( $this->savepoint_counter < 1 ){
			$this->savepoint_counter = 0;
			$this->rollback_transaction();
		}
	}
}
