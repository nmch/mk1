<?php
return [
	'active'  => 'default',
	'default' => [
		'connection'             => [
			'host'     => getenv('PGHOST'),
			'user'     => getenv('PGUSER'),
			'password' => getenv('PGPASSWORD'),
			'dbname'   => getenv('PGDATABASE'),
		],
		'connect_retry'          => 10,
		'connect_retry_interval' => 2,
	],
];
