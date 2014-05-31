<?
class Session
{
	use Singleton;
	protected static $config = array();
	protected static $driver;
	
	protected static $flash = array();
	
	function __construct()
	{
		$config = array_merge(array(
		),Config::get('session'));
		static::$config = $config;
		
		$driver_name = Arr::get($config,'driver');
		$driver_config = Arr::get($config,$driver_name ?: '_default');
		if( ! $driver_config )
			throw new Exception('driver config not found');
		// ドライバ名が指定されていた場合のみPHPのデフォルトセッションハンドラを変更する
		if($driver_name){
			$driver_class_name = "Session_Driver_".ucfirst(strtolower($driver_name));
			if( ! class_exists($driver_class_name) ){
				throw new Exception('driver not found');
			}
			if( ! is_subclass_of($driver_class_name, 'SessionHandlerInterface') ){
				throw new Exception('driver should extend SessionHandlerInterface');
			}
			$driver = new $driver_class_name($driver_config);
			
			session_set_save_handler($driver);
		}
		
		
		$cookie_name = Arr::get($driver_config,'cookie_name');
		if( ! $cookie_name )
			throw new Exception('cookie name not found');
		
		session_name($cookie_name);
		session_set_cookie_params($config['expiration_time'],$config['cookie_path'],$config['cookie_domain']);
		session_start();
		
		Log::coredebug("Session started : ".session_id());
		
		// Flashデータをロード
		static::$flash = static::get(static::flash_id());
		if( ! is_array(static::$flash) )
			static::$flash = array();
	}
	static function __callStatic($name, $arguments)
	{
		return call_user_func(array(self::instance(),$name),$arguments);
	}
	
	static function flash_id()
	{
		return static::$config['flash_id'];;
	}
	/**
	 * フラッシュセッションデータをセットする
	 *
	 * コントローラやビュー内でのセット/ゲットをサポートするため
	 * static::$flash と static::set()の両方を呼び出している
	 */
	static function set_flash($name,$value)
	{
		$flash = static::get_flash();
		$flash[$name] = $value;
		static::set(static::flash_id(),$flash);
		static::$flash = $flash;
	}
	/**
	 * フラッシュセッションデータを得る
	 *
	 * フラッシュデータは常にstatic::$flashからのみ読み込む
	 */
	static function get_flash($name = NULL,$default = NULL)
	{
		if($name){
			return Arr::get(static::$flash,$name,$default);
		}
		else{
			return static::$flash;
		}
	}
	/**
	 * フラッシュセッションデータを削除する
	 *
	 * ロードされたフラッシュデータはセッションからは消去される。
	 * Viewから呼び出される。
	 */
	static function clear_flash()
	{
		static::delete(static::flash_id());
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
		$value = Arr::get($_SESSION,$name,$default);
		//Log::coredebug("[session] get $name : ",$value);
		return $value;
		//return array_key_exists($name,$_SESSION) ? $_SESSION[$name] : $default;
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
