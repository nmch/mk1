<?
class Session
{
	use Singleton;
	protected static $config = array();
	
	protected static $flash = array();
	
	function __construct()
	{
		$config = array_merge(array(
		),Config::get('session'));
		static::$config = $config;
		
		session_name($config[$config['driver']]['cookie_name']);
		session_set_cookie_params($config['expiration_time'],$config['cookie_path'],$config['cookie_domain']);
		session_start();
		Log::coredebug("Session started : ".session_id());
	}
	static function __callStatic($name, $arguments)
	{
		return call_user_func(array(self::instance(),$name),$arguments);
	}
	
	static function set_flash($name,$value)
	{
		$flash_id = static::$config['flash_id'];
		$flash = static::get($flash_id);
		if( ! is_array($flash) )
			$flash = array();
		$flash[$name] = $value;
		static::set(static::$config['flash_id'],$flash);
		//Log::coredebug("[session flash] set $name",$value,$flash);
	}
	static function get_flash($name,$default = NULL)
	{
		//Log::coredebug("[session flash] get $name",Arr::get(static::$flash,$name));
		return array_key_exists($name,static::$flash) ? static::$flash[$name] : $default;
	}
	/**
	 * フラッシュセッションデータをロードする
	 *
	 * ロードされたフラッシュデータはセッションからは消去される。
	 * Viewから呼び出される。
	 */
	static function load_flash()
	{
		$flash_id = static::$config['flash_id'];
		static::$flash = static::get($flash_id);
		if( ! is_array(static::$flash) )
			static::$flash = array();
		static::delete($flash_id);
	}
	
	static function set($name,$value)
	{
		if( ! is_scalar($name) )
			throw new Exception('invalid name');
		$_SESSION[$name] = $value;
		return $_SESSION[$name];
	}
	static function get($name,$default = NULL)
	{
		return array_key_exists($name,$_SESSION) ? $_SESSION[$name] : $default;
	}
	static function delete($name)
	{
		if(array_key_exists($name,$_SESSION))
			unset($_SESSION[$name]);
	}
	static function destroy()
	{
		session_destroy();
	}
}
