<?php

class Dynamodb
{
	/** @var \Aws\DynamoDb\DynamoDbClient */
	public $client;
	
	function __construct(?string $name = null)
	{
		$all_config = \Config::get("dynamodb");
		$config     = $all_config[$name ?? ($all_config['active'] ?? 'default')] ?? null;
		if( ! $config ){
			throw new MkException("empty dynamodb config");
		}
		
		$endpoint = $config['connection']['endpoint'] ?? null;
		if( ! $endpoint ){
			throw new MkException("empty dynamodb endpoint");
		}
		
		$credentials = $config['connection']['credentials'] ?? null;
		
		$this->client = (new \Aws_Sdk())
			->set_credentials($credentials)
			->get_dynamodb_client($endpoint)
		;
	}
	
	function get_marshaler(): \Aws\DynamoDb\Marshaler
	{
		$marshaler = new \Aws\DynamoDb\Marshaler(['nullify_invalid' => true]);
		
		return $marshaler;
	}
	
	function find_all(array $params, $method = "scan", $yield_per_page = false)
	{
		$next_token = null;
		do{
			if( $next_token ){
				$params['ExclusiveStartKey'] = $next_token;
			}
			
			if( $method === 'scan' ){
				$r = $this->scan($params);
			}
			else{
				$r = $this->query($params);
			}
			
			if( $r['LastEvaluatedKey'] ?? null ){
				$next_token = $r['LastEvaluatedKey'];
			}
			else{
				$next_token = null;
			}
			
			if( $yield_per_page ){
				yield $r;
			}
			else{
				foreach($r['Items'] ?? [] as $raw_item){
					yield $raw_item;
				}
			}
		} while($next_token);
	}
	
	function query(array $params)
	{
		return $this->client->query($params);
	}
	
	function scan(array $params)
	{
		return $this->client->scan($params);
	}
	
	function describe_table(array $params)
	{
		$r = $this->client->describeTable($params);
		
		return $r['Table'];
	}
	
	function delete_table(array $params)
	{
		$r = $this->client->deleteTable($params);
		
		return $r;
	}
	
	function create_table(array $params, $replace = false)
	{
		if( $replace ){
			$table_name = $params['TableName'];
			if( $this->table_exists($table_name) ){
				$this->delete_table(['TableName' => $table_name]);
			}
		}
		
		$r = $this->client->createTable($params);
		
		return $r;
	}
	
	function update_item($params)
	{
		return $this->client->updateItem($params);
	}
	
	function list_tables(array $params = [])
	{
		while(true){
			$r = $this->client->listTables($params);
			
			$tables = $r['TableNames'] ?? [];
			foreach($tables as $table){
				yield $table;
			}
			
			if( $r['LastEvaluatedTableName'] ){
				$params['ExclusiveStartTableName'] = $r['LastEvaluatedTableName'];
			}
			else{
				break;
			}
		}
	}
	
	function table_exists(string $target_table_name): bool
	{
		// テーブルの存在を確認するAPIがなく、DescribeTableではエラーメッセージの文字列比較しか不存在を確認する方法がないので
		// より確実なテーブル一覧を確認する方法を採る
		
		foreach($this->list_tables() as $table_name){
			if( $table_name === $target_table_name ){
				return true;
			}
		}
		
		return false;
	}
	
	function create_table_if_not_exists(string $table_name, array $table_config)
	{
		$dynamodb_client = $this->client;
		
		if( ! $this->table_exists($table_name) ){
			$table_config += [
				'TableName' => $table_name,
			];
			
			$dynamodb_client->createTable($table_config);
		}
	}
}
