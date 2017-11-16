<?php

/**
 * ログ
 *
 * @method static void emergency($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void alert($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void critical($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void error($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void warning($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void notice($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void info($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug1($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug2($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug3($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug4($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void debug5($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 * @method static void coredebug($message1, $message2 = null, $message3 = null, $message4 = null, $message5 = null)
 */
class Log
{
	use Singleton;
	const ALL       = 0;
	const COREDEBUG = 100;
	const DEBUG     = 200;
	const DEBUG5    = 214;
	const DEBUG4    = 215;
	const DEBUG3    = 216;
	const DEBUG2    = 217;
	const DEBUG1    = 218;
	const INFO      = 300;
	const NOTICE    = 350;
	const WARNING   = 400;
	const ERROR     = 500;
	const CRITICAL  = 600;
	const ALERT     = 650;
	const EMERGENCY = 700;
	static         $threshold;
	static         $date_format;
	static         $makelogstr_function;
	static private $drivers = [];

	function __construct()
	{
		$drivers = Config::get('log.drivers', []);
		if( is_array($drivers) ){
			foreach($drivers as $index => $config_value){
				/**
				 * driver_name => threshold 形式と
				 * index => config array 形式に対応する
				 * $config_valueが配列かどうかで判断する
				 * nullの場合は未定義として処理を飛ばす
				 */
				if( $config_value === null ){
					continue;
				}

				$global_driver_config = Config::get('log', []);

				if( is_array($config_value) ){
					$driver_config = $config_value + $global_driver_config;
				}
				else{
					$driver_config = [
						                 'name'      => $index,
						                 'threshold' => $config_value,
					                 ] + $global_driver_config;
				}

				$driver_name = Arr::get($driver_config, 'driver') ?: Arr::get($driver_config, 'name');
				if( ! strlen($driver_name) ){
					throw new MkException('invalid driver name');
				}
				$driver_name                    = 'Log_' . ucfirst($driver_name);
				$driver_config['uniqid']        = uniqid();
				$driver_config['driver_object'] = new $driver_name($driver_config);

				static::$drivers[] = $driver_config;
			}
		}
		static::$date_format = Config::get('log.date_format', 'Y-m-d H:i:s');
		//static::$makelogstr_function = ['Log','make_log_string'];
		static::set_make_log_string_function(['Log', 'make_log_string']);
	}

	/**
	 * 以降のログ記録を抑制する
	 *
	 * @param null $target_name
	 * @param null $target_driver
	 */
	public static function suppress($target_name = null, $target_driver = null)
	{
		foreach(static::$drivers as $index => $driver_config){
			$name = Arr::get($driver_config, 'name');
			if( $target_name && $target_name !== $name ){
				continue;
			}

			$driver = Arr::get($driver_config, 'driver');
			if( $target_driver && $target_driver !== $driver ){
				continue;
			}

			Log::info("Log [name={$name} / driver={$driver}] suppressed");
			static::$drivers[$index]['suppress'] = true;
		}
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
			if( is_object($data['message']) && method_exists($data['message'], '__toString') ){
				$data['message'] = (string)$data['message'];
			}
			else{
				$data['message'] = var_export($data['message'], true);
			}
		}

		$log_str = $log_format = Arr::get($data, 'config.log_format');
		if( preg_match_all('/\{([a-z0-9_.]+|@[^}]+)}/', $log_format, $vars) ){
			foreach($vars[1] as $var){
				$var_value = '';

				if( strlen($var) >= 1 && $var[0] === '@' ){
					// 先頭に@があった場合はPHPの関数として解釈する
					$php_function = 'return ' . ltrim($var, '@') . ';';
					try {
						$var_value = eval($php_function);
					} catch(Exception $e){
						$var_value = $var;
					}
				}
				else{
					$var_value = Arr::get($data, $var);
				}

				$log_str = str_replace('{' . $var . '}', $var_value, $log_str);
			}
		}

		return $log_str;
	}

	static function log($level, $message)
	{
		try {
			$log = static::instance();

			$level            = strtoupper($level);
			$level_const_name = 'Log::' . $level;
			if( ! defined($level_const_name) ){
				throw new MkException('undefined log level');
			}
			$level_num = constant($level_const_name);
			if( $level_num < static::$threshold ){
				return false;
			}

			$messages = [$message];
			if( func_num_args() > 2 ){
				$messages = array_merge($messages, array_slice(func_get_args(), 2));
			}

			foreach($messages as $message){
				$timestamp_unixtime = time();
				// キーで使える文字はmake_log_string()内で[a-z0-9_.]に制限されている
				/** @see \Log::make_log_string */
				$base_log_data = [
					'timestamp_unixtime' => $timestamp_unixtime,
					'timestamp_string'   => date(static::$date_format, $timestamp_unixtime),
					'level'              => $level,
					'level_num'          => $level_num,
					'message'            => $message,
				];

				foreach(static::$drivers as $driver_config){
					if( Arr::get($driver_config, 'suppress') ){
						continue;
					}
					if( $driver_config['threshold'] <= $level_num ){
						$log_data        = $base_log_data + [
								'config' => $driver_config,
							];
						$log_data['str'] = call_user_func_array(static::$makelogstr_function, [$log_data]);

						/** @var Logic_Interface_Log_Driver $driver_config */
						$driver_config = $driver_config['driver_object'];
						$driver_config->write($log_data);
					}
				}

				unset($log_data);
				unset($log_str);
			}
			unset($messages);
		} catch(Exception $e){
			echo $e;
		}
	}

	public static function __callStatic($name, $arguments)
	{
		return call_user_func_array(['static', 'log'], array_merge([$name], $arguments));
	}
}
