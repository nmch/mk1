<?
class Error
{
	private static $shutdown_handler = array();
	private static $exception_handler = array();
	private static $error_handler = array();
	
	static function exception_handler($e)
	{
		Log::error((string)$e);
		foreach(Arr::merge(static::$exception_handler,static::$error_handler) as $function)
			$function($e);
		
		//throw $e;
	}
	static function shutdown_handler()
	{
		$last_error = error_get_last();
		if($last_error){
			Log::error($last_error);
			foreach(Arr::merge(static::$shutdown_handler,static::$error_handler) as $function)
				$function($last_error);
		}
	}
	
	static function add_shutdown_handler($function)
	{
		static::$shutdown_handler[] = $function;
	}
	static function add_exception_handler($function)
	{
		static::$exception_handler[] = $function;
	}
	static function add_error_handler($function)
	{
		static::$error_handler[] = $function;
	}
}
