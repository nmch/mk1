<?php
return [
	// 共通設定
	'auto_initialize'        => true,
	'driver'                 => '',
	'cookie_domain'          => '',
	'cookie_path'            => '/',
	'cookie_secure'          => false,
	'cookie_httponly'        => true,
	'expire_on_close'        => false,
	'expiration_time'        => 86400 * 365 * 2,
	'flash_id'               => 'flash',
	'flash_auto_expire'      => true,
	'flash_expire_after_get' => true,
	
	// PHPデフォルトセッションハンドラ
	'_default'               => [
		'cookie_name'    => 'mkfid',
		'path'           => '/tmp',
		'gc_probability' => 5,
	],
	
	// memcached
	'memcached'              => [
		'cookie_name' => 'mkmid',
		'servers'     => ['default' => ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]],
	],
	
	// DB
	'db'                     => [
		'cookie_name'    => 'mkdid',
		'database'       => null,
		'table'          => 'sessions',
		'gc_probability' => 5,
	],
];
