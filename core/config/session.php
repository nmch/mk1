<?php
return array(
	'auto_initialize'	=> true,
	'driver'			=> 'file',
	'cookie_domain' 	=> '',
	'cookie_path'		=> '/',
	'expire_on_close'	=> false,
	'expiration_time'	=> 0,
	'flash_id'			=> 'flash',
	'flash_auto_expire'	=> true,
	'flash_expire_after_get' => true,
	'file'				=> array(
		'cookie_name'		=> 'mkfid',
		'path'				=>	'/tmp',
		'gc_probability'	=>	5
	),
	'memcached'			=> array(
		'cookie_name'		=> 'mkmid',
		'servers'			=> array(
								'default' => array('host' => '127.0.0.1', 'port' => 11211, 'weight' => 100)
							),
	),
	'db'			=> array(
		'cookie_name'		=> 'mkdid',
		'database'			=> null,
		'table'				=> 'sessions',
		'gc_probability'	=> 5
	),
);


