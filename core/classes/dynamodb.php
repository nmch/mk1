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
	
	function list_tables()
	{
		$params = [
			'Limit' => 1,
		];
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
}
