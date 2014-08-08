<?
class Log
{
	use Singleton;
	static private $drivers = array();
	static $threshold;
	static $date_format;
	static $makelogstr_function;
	
	const ALL		= 0;
	const COREDEBUG	= 100;
	const DEBUG		= 200;
	const INFO		= 300;
	const NOTICE	= 350;
	const WARNING	= 400;
	const ERROR		= 500;
	const CRITICAL	= 600;
	const ALERT		= 650;
	const EMERGENCY	= 700;
	
	function __construct()
	{
		$drivers = Config::get('log.drivers',array());
		if(is_array($drivers)){
			foreach($drivers as $driver_name => $threshold){
				$driver_name = 'Log_'.ucfirst($driver_name);
				static::$drivers[$driver_name] = array(
					'threshold' => $threshold,
					'driver' => new $driver_name,
				);
			}
		}
		static::$date_format = Config::get('log.date_format','Y-m-d H:i:s');
		//static::$makelogstr_function = ['Log','make_log_string'];
		static::set_make_log_string_function(['Log','make_log_string']);
	}
	
	static function set_make_log_string_function($function_name)
	{
		static::$makelogstr_function = $function_name;
	}
	
	/**
	 * ログに記録する文字列を生成する
	 *
	 * set_make_log_string_function()で書き換え可能
	 */
	static function make_log_string($data)
	{
		if( ! is_scalar($data['message']) ){
			$data['message'] = var_export($data['message'],true);
		}
		
		$log_str = $log_format = Config::get('log.log_format');
		if(preg_match_all('/\{([a-z0-9_]+|@[^}]+)}/',$log_format,$vars)){
			foreach($vars[1] as $var){
				$var_value = '';
				
				if(strlen($var) >= 1 && $var[0] === '@'){
					// 先頭に@があった場合はPHPの関数として解釈する
					$php_function = 'return '.ltrim($var, '@').';';
					try {
						$var_value = eval($php_function);
					} catch(Exception $e){
						$var_value = $var;
					}
				}
				else{
					$var_value = Arr::get($data, $var);
				}
				
				$log_str = str_replace('{'.$var.'}',$var_value,$log_str);
			}
		}
		
		return $log_str;
	}
	
	static function log($level,$message)
	{
		try {
			$log = static::instance();
			
			$level = strtoupper($level);
			$level_const_name = 'Log::'.$level;
			if( ! defined($level_const_name) )
				throw new MkException('undefined log level');
			$level_num = constant($level_const_name);
			if($level_num < static::$threshold)
				return FALSE;
			
			$messages = array($message);
			if(func_num_args() > 2)
				$messages = array_merge($messages,array_slice(func_get_args(),2));
			
			foreach($messages as $message){
				$timestamp_unixtime = time();
				// キーで使える文字はmake_log_string()内で[a-z0-9_]に制限されている
				$log_data = [
					'timestamp_unixtime'=> $timestamp_unixtime,
					'timestamp_string'	=> date(static::$date_format, $timestamp_unixtime),
					'level'				=> $level,
					'level_num'			=> $level_num,
					'message'			=> $message
				];
				$log_data['str'] = call_user_func_array(static::$makelogstr_function,[$log_data]);
				
				foreach(static::$drivers as $driver){
					if($driver['threshold'] <= $level_num){
						$driver['driver']->write($log_data);
					}
				}
				
				unset($log_data);
				unset($log_str);
			}
			unset($messages);
		}
		catch (Exception $e){
			echo $e;
		}
	}
	
	public static function __callStatic($name, $arguments)
	{
		return call_user_func_array(array('static','log'),array_merge(array($name),$arguments));
	}
}
