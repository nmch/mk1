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
		
		$driver_name = Arr::get($config,'driver');
		if( ! $driver_name )
			throw new Exception('invalid driver name');
		$driver_config = Arr::get($config,$driver_name);
		if( ! $driver_config )
			throw new Exception('driver config not found');
		$cookie_name = Arr::get($driver_config,'cookie_name');
		if( ! $cookie_name )
			throw new Exception('cookie name not found');
		
		session_name($cookie_name);
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
		//Log::coredebug("[load flash] $flash_id ",static::$flash);
		if( ! is_array(static::$flash) )
			static::$flash = array();
		static::delete($flash_id);
	}
	
	static function set($name,$value)
	{
		if( ! is_scalar($name) )
			throw new Exception('invalid name');
		$_SESSION[$name] = $value;
		//Log::coredebug("[session] set $name : ",$value);
		return $_SESSION[$name];
	}
	static function get($name,$default = NULL)
	{
		return array_key_exists($name,$_SESSION) ? $_SESSION[$name] : $default;
	}
	static function delete($name)
	{
		if(array_key_exists($name,$_SESSION)){
			unset($_SESSION[$name]);
			//Log::coredebug("[session] $name deleted",$_SESSION);
		}
	}
	static function destroy()
	{
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		
		if(session_status() === PHP_SESSION_ACTIVE)
			session_destroy();
	}
}
