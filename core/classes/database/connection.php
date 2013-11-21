<?
class Database_Connection
{
	static $instances = array();
	private $connection;
	private $savepoint_counter = 0;
	
	function __construct($config)
	{
		if(empty($config['connection']))
			$config['connection'] = "";
		
		$connection_config = "";
		if(is_array($config['connection'])){
			foreach($config['connection'] as $key => $value){
				$connection_config .= "$key=$value ";
			}
		}
		else
			$connection_config = $config['connection'];
		
		//Log::coredebug("[db connection] try connect to $connection_config");
		$this->connection = pg_connect($connection_config);
		pg_set_client_encoding($this->connection, 'UTF-8');
		if($this->connection === false)
			throw new MkException('failed establish to db');
		return $this;
	}
	static function instance($name = NULL)
	{
		if( ! $name )
			$name = Config::get('db.active');
		//Log::coredebug("[db connection] try get a connection named $name");
		if(empty(static::$instances[$name])){
			$config = \Config::get("db.{$name}");
			static::$instances[$name] = new static($config);
		}
		
		return static::$instances[$name];
	}
	function dbname()
	{
		return pg_dbname($this->connection);
	}
	function copy_from($table_name , $rows)
	{
		return pg_copy_from($this->connection, $table_name , $rows);
	}
	function query($sql,$parameters = array())
	{
		Log::coredebug("[dbconn] SQL {$this->connection} = $sql / ".var_export($parameters,true));
		if($parameters)
			$r = pg_query_params($this->connection,$sql,$parameters);
		else
			$r = pg_query($this->connection,$sql);
		
		if($r === false)
			throw new DatabaseQueryError(pg_last_error($this->connection));
		return new Database_Resultset($r);
	}
	function get_transaction_status()
	{
		return pg_transaction_status($this->connection);
	}
	function in_transaction()
	{
		return ($this->get_transaction_status() != PGSQL_TRANSACTION_IDLE);
	}
	function start_transaction()
	{
		DB::query("BEGIN")->execute($this);
	}
	function commit_transaction()
	{
		DB::query("COMMIT")->execute($this);
	}
	function rollback_transaction()
	{
		DB::query("ABORT")->execute($this);
	}
	
	function place_savepoint()
	{
		if( ! $this->in_transaction() )
			$this->start_transaction();
		
		$point_name = preg_replace('/[^0-9a-z]/','',strtolower(uniqid(gethostname())));
		DB::query('SAVEPOINT '.$point_name)->execute($this);
		Log::coredebug('[dbconn] SAVEPOINT '.$point_name);
		$this->savepoint_counter++;
		return $point_name;
	}
	function commit_savepoint($point_name)
	{
		if( ! $this->in_transaction() )
			throw new MkException('not in transaction');
		if( ! $point_name )
			throw new MkException('invalid point name');
		
		DB::query('RELEASE SAVEPOINT '.$point_name)->execute($this);
		$this->savepoint_counter--;
		if($this->savepoint_counter < 1){
			$this->savepoint_counter = 0;
			$this->commit_transaction();
		}
	}
	function rollback_savepoint($point_name)
	{
		if( ! $this->in_transaction() )
			throw new MkException('not in transaction');
		if( ! $point_name )
			throw new MkException('invalid point name');
		
		DB::query('ROLLBACK TO SAVEPOINT '.$point_name)->execute($this);
		$this->savepoint_counter--;
	}
}
