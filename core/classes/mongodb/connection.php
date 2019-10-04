<?php

class Mongodb_Connection
{
	/** @var Mongodb_Connection[] $instances */
	static $instances = [];
	/** @var \MongoDB\Client $connection */
	private $connection;
	
	function __construct(array $config)
	{
		$connection_url = static::get_connection_url($config);
		$database_name  = static::get_database_name($config);
		
		//Log::coredebug("[db connection] try connect to [{$connection_config}]");
		$connect_retry          = intval(Arr::get($config, 'connect_retry'), 0);
		$connect_retry_interval = intval(Arr::get($config, 'connect_retry_interval'), 0);
		$retry_count            = 0;
		
		/** @var Exception $last_error */
		$last_error = null;
		do{
			if( $retry_count ){
				Log::warning("MongoDB接続再試行[{$retry_count}]", $last_error);
			}
			try {
				$client           = new \MongoDB\Client($connection_url);
				$this->connection = $client->selectDatabase($database_name);
				$last_error       = null;
				break;
			} catch(Exception $e){
				$last_error = $e;
				
				if( $connect_retry_interval ){
					sleep($connect_retry_interval);
				}
			}
			$retry_count++;
		} while($retry_count < $connect_retry);
		if( $last_error ){
			throw $last_error;
		}
		
		if( $this->connection === false ){
			$connection_url_to_display = preg_replace("/:[^ ]+@/", ":*SECRET*@", $connection_url);
			throw new MkException("failed establish to mongodb (connection url = [{$connection_url_to_display}])");
		}
		
		return $this;
	}
	
	static function get_database_name(array $config)
	{
		if( $database = ($config['connection']['dbname'] ?? null) ){
			return $database;
		}
		else{
			throw new MkException("empty mongodb database config");
		}
	}
	
	static function get_connection_url(array $config): string
	{
		$url = "mongodb://";
		
		if( $user = ($config['connection']['user'] ?? null) ){
			$url .= "{$user}:";
			if( $password = ($config['connection']['password'] ?? null) ){
				$url .= $password;
			}
			$url .= "@";
		}
		$host = ($config['connection']['host'] ?? 'localhost');
		$url  .= $host;
		
		return $url;
	}
	
	function get_connection()
	{
		return $this->connection;
	}
	
	static function get_config($name = null): array
	{
		if( ! $name ){
			$name = Config::get('mongodb.active', 'default');
		}
		$config = \Config::get("mongodb.{$name}", []);
		
		return $config;
	}
	
	/**
	 * @param string|null $name
	 *
	 * @param bool        $force_new
	 *
	 * @return Mongodb_Connection
	 * @throws MkException
	 */
	static function instance($name = null, $force_new = false): Mongodb_Connection
	{
		$config = static::get_config($name);
		
		if( $force_new ){
			return new static($config);
		}
		else{
			if( empty(static::$instances[$name]) ){
				static::$instances[$name] = new Mongodb_Connection($config);
			}
			
			return static::$instances[$name];
		}
	}
}
