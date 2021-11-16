<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class ErrorHandler
{
	private static $shutdown_handler  = [];
	private static $exception_handler = [];
	private static $error_handler     = [];
	
	static function exception_handler($e)
	{
		$log_level = ($e instanceof MkException ? $e->log_level : \Log::LEVEL_ERROR) ?? \Log::LEVEL_ERROR;
		Log::log($log_level, $e->getMessage(), [
			'code' => $e->getCode(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		], $e);
		
		foreach(Arr::merge(static::$exception_handler, static::$error_handler) as $function){
			$function($e);
		}
	}
	
	static function shutdown_handler()
	{
		$last_error = error_get_last();
		if( $last_error ){
			$e = new Exception();
			Log::error($last_error, "\n" . $e->getTraceAsString());
			foreach(Arr::merge(static::$shutdown_handler, static::$error_handler) as $function){
				$function($last_error);
			}
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
