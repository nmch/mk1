<?php

class Session_Driver_Dynamodb
{
	static function instance(array $driver_config): \Aws\DynamoDb\SessionHandler
	{
		$dynamodb        = new Dynamodb($driver_config['connection'] ?? null);
		$dynamodb_client = $dynamodb->client;
		
		$table_name = $driver_config['table'] ?? 'sessions';
		
		$params  = $driver_config['handler_config'] ?? [];
		$params  += [
			'table_name' => $table_name,
		];
		$handler = \Aws\DynamoDb\SessionHandler::fromClient($dynamodb_client, $params);
		
		return $handler;
	}
}