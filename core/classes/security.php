<?php

class Security
{
	protected static $csrf_token;
	
	protected static function get_csrf_token_key()
	{
		return Config::get('security.csrf_token_key', 'mk_csrf_token');
	}
	
	public static function fetch_token()
	{
		$token          = static::generate_token();
		$csrf_token_key = static::get_csrf_token_key();
		Session::set_flash($csrf_token_key, $token);
		
		return $token;
	}
	
	public static function generate_token()
	{
		$token_base   = \Config::get('security.token_salt', '') . random_bytes(64);
		$hashed_token = hash('sha512', $token_base);
		
		return $hashed_token;
	}
}
