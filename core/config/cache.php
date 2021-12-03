<?php
return [
	'driver' => 'file',
	
	'global_config' => [
		// キャッシュ処理中に発生した例外をログ記録するか (ログレベルを設定)
		'log_on_exception'   => Log::LEVEL_ERROR,
		// キャッシュ処理中に発生した例外をスローするか
		'throw_on_exception' => false,
	],
	'driver_config' => [
		'file'  => [
			'cache_dir'      => PROJECTPATH . 'tmp/cache/',
			'default_expire' => 3600,
		],
		'redis' => [
			'endpoint'    => getenv('REDIS_ENDPOINT'),
			'port'        => getenv('REDIS_PORT'),
			'timeout'     => 3,
			'default_ttl' => 3600,
		],
	],
];
