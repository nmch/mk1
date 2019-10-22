<?php

class Session_Driver_Dynamodb
{
	static function instance(array $driver_config): \Aws\DynamoDb\SessionHandler
	{
		$dynamodb        = new Dynamodb($driver_config['connection'] ?? null);
		$dynamodb_client = $dynamodb->client;
		
		$table_name = $driver_config['table'] ?? 'sessions';
		
		static::create_table_if_not_exists($dynamodb, $table_name, $driver_config);
		
		$params  = $driver_config['handler_config'] ?? [];
		$params  += [
			'table_name' => $table_name,
		];
		$handler = \Aws\DynamoDb\SessionHandler::fromClient($dynamodb_client, $params);
		
		return $handler;
	}
	
	static function create_table_if_not_exists(Dynamodb $dynamodb, string $table_name, array $driver_config)
	{
		$dynamodb_client = $dynamodb->client;
		
		if( ! $dynamodb->table_exists($table_name) ){
			$params = $driver_config['table_config'] ?? [];
			$params += [
				'TableName' => $table_name,
			];
			
			$dynamodb_client->createTable($params);
		}
	}
}