<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

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
		
		Session::set($csrf_token_key, $token);
		
		return $token;
	}
	
	public static function generate_token()
	{
		$token_base   = \Config::get('security.token_salt', '') . random_bytes(64);
		$hashed_token = hash('sha512', $token_base);
		
		return $hashed_token;
	}
	
	static function check_token($submitted_token)
	{
		$csrf_token_key = static::get_csrf_token_key();
		$saved_token    = Session::get($csrf_token_key);
		
		Session::delete($csrf_token_key);
		
		if( ! strlen($submitted_token) || ! strlen($saved_token) ){
			Log::coredebug("[Security] CSRFトークンが不正です saved_token=[{$saved_token}] / submitted_token=[{$submitted_token}]");
			throw new InvalidCsrfTokenException("invalid CSRF Token");
		}
		if( $submitted_token !== $saved_token ){
			Log::coredebug("[Security] CSRFトークンが一致しません saved_token=[{$saved_token}] / submitted_token=[{$submitted_token}]");
			throw new InvalidCsrfTokenException("CSRF Token mismatch");
		}
	}
}
