<?
class Log
{
	use Singleton;
	static private $drivers = array();
	static $threshold;
	static $date_format;
	
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
				self::$drivers[$driver_name] = array(
					'threshold' => $threshold,
					'driver' => new $driver_name,
				);
			}
		}
		self::$date_format = Config::get('log.date_format','Y-m-d H:i:s');
	}
	
	static function log($level,$message)
	{
		$log = self::instance();
		
		$level = strtoupper($level);
		$level_const_name = 'Log::'.$level;
		if( ! defined($level_const_name) )
			throw new MkException('undefined log level');
		$level_num = constant($level_const_name);
		if($level_num < self::$threshold)
			return FALSE;
		
		$messages = array($message);
		if(func_num_args() > 2)
			$messages = array_merge($messages,array_slice(func_get_args(),2));
		
		foreach($messages as $message){
			$log_str = "$level - ".date(self::$date_format).' --> ';
			if(is_scalar($message))
				$log_str .= $message;
			else
				$log_str .= var_export($message,true);
			//$log_str .= "\n".print_r(debug_backtrace(0,3),true);
			
			$log_data = array(
				'level' => $level,
				'level_num' => $level_num,
				'str' => $log_str
			);
			
			foreach(self::$drivers as $driver){
				if($driver['threshold'] <= $level_num)
					$driver['driver']->write($log_data);
			}
			
			unset($log_data);
			unset($log_str);
		}
		unset($messages);
	}
	
	public static function __callStatic($name, $arguments)
	{
		return call_user_func_array(array('static','log'),array_merge(array($name),$arguments));
	}
}
