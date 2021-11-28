<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Mk
{
	use Singleton;

	const DEVELOPMENT = 'development';
	const TEST        = 'test';
	const PRODUCTION  = 'production';
	const STAGE       = 'stage';
	public static $env      = Mk::PRODUCTION;
	public static $include_path_list;
	public static $session;
	public static $encoding = 'UTF-8';
	public        $config;

	function __construct()
	{
		// 実行環境の決定
		static::$env = static::PRODUCTION;                                // 何も指定されていない場合のデフォルト動作はPRODUCTION
		static::$env = getenv('FUEL_ENV') ?: static::$env;    // 環境変数
		static::$env = getenv('MK_ENV') ?: static::$env;    // 環境変数
		static::$env = get_cfg_var('MK_ENV') ?: static::$env;    // AWS EBSのEC2でCLI実行した場合
		static::$env = Arr::get($_SERVER, 'MK_ENV') ?: static::$env;    // サーバ変数
		// ユニットテスト時、環境名がtest以外だった場合はその環境名に-testをつけた環境名にする。
		if( static::is_unittesting() && static::$env !== 'test' ){
			static::$env = Mk::env() . '-test';
		}
		// 本番環境以外では実行環境の書き換えに対応
		if( ! static::is_production() ){
			static::$env = Arr::get($_REQUEST, 'MK_ENV') ?: static::$env;
		}

		self::$include_path_list = self::get_include_path_list();
		$this->config            = Config::instance();
		$start_log               = "Mk1 Start ";
		$start_log               .= "(env:" . self::$env . (self::is_production() ? ' [PRODUCTION]' : '') . ")";
		$start_log               .= " ============================================";
		Log::coredebug($start_log);
		//Log::coredebug("[mk] env=" . self::$env . (self::is_production() ? ' [PRODUCTION]' : ''));
		if( self::is_production() ){
			error_reporting(0);
			ini_set('display_errors', 0);
		}
		else{
			error_reporting(E_ALL);
		}

		$locale = setlocale(LC_ALL, Config::get('locale', 'en_US'));
		//Log::coredebug("locale=$locale");

		// Sentry初期化
		Sentry::instance();

		/**
		 * パッケージごとのvendorオートローダーを実行
		 */
		foreach(static::package_directories() as $dir){
			$vendor_autoload_filepath = "{$dir}/vendor/autoload.php";
			if( file_exists($vendor_autoload_filepath) ){
				include($vendor_autoload_filepath);
			}
		}

		/**
		 * パッケージごとのbootstrapを実行
		 */
		foreach(static::package_directories() as $dir){
			$bootstrap_filepath = "{$dir}/bootstrap.php";
			if( file_exists($bootstrap_filepath) ){
				include($bootstrap_filepath);
			}
		}

		/**
		 * セッション自動開始
		 *
		 * ユニットテストモード以外のCLI環境では開始しない
		 */
		if( Config::get('session.auto_initialize') && (php_sapi_name() !== 'cli' || \Mk::is_unittesting()) ){
			self::$session = Session::instance();
		}
	}

	static function retry(callable $try_function, array $try_function_params = [], int $max_retry_count = 3, int $retry_interval_sec = 1, ?callable $retry_callback = null, ?callable $exception_callback = null)
	{
		if( ! $exception_callback ){
			$exception_callback = function(Exception $e){
				return true; // trueを返すとリトライ
			};
		}
		if( ! $retry_callback ){
			$retry_callback = function(int $retry_count, Exception $e){
				Log::coredebug("Retry [{$retry_count}]: {$e->getMessage()}");
			};
		}

		/** @var Exception $last_error */
		$result      = null;
		$last_error  = null;
		$retry_count = 0;
		do{
			if( $retry_count ){
				$retry_callback($retry_count, $last_error);
			}
			try {
				$result     = call_user_func_array($try_function, $try_function_params);
				$last_error = null;
				break;
			} catch(Exception $e){
				$last_error = $e;

				if( ! $exception_callback($e) ){
					break;
				}

				if( $retry_interval_sec ){
					sleep($retry_interval_sec);
				}
			}
			$retry_count++;
		} while($retry_count < $max_retry_count);

		if( $last_error ){
			throw $last_error;
		}

		return $result;
	}

	static function package_directories()
	{
		foreach(glob(PKGPATH . '*', GLOB_ONLYDIR) as $dir){
			yield $dir;
		}
	}

	static function is_unittesting()
	{
		return UNITTESTMODE;
	}

	static function is_cli()
	{
		return php_sapi_name() === 'cli';
	}

	static function env($env = null)
	{
		if( $env ){
			static::$env = $env;
		}

		return static::$env;
	}

	/**
	 * 本番環境かどうかの判定
	 *
	 * 環境変数がproductionから始まる場合は真
	 */
	static function is_production()
	{
		return (strncmp(strtolower(self::$env), static::PRODUCTION, strlen(static::PRODUCTION)) === 0);
	}

	static function json_decode($value)
	{
		return json_decode($value, true, 512, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_THROW_ON_ERROR);
	}

	static function json_encode($value): string
	{
		$json = json_encode($value, JSON_HEX_TAG
		                            | JSON_HEX_APOS
		                            | JSON_HEX_QUOT
		                            | JSON_HEX_AMP
		                            | JSON_PARTIAL_OUTPUT_ON_ERROR);
		if( $json === false ){
			throw new UnexpectedValueException();
		}

		return $json;
	}

	// 優先度 低→高の並び
	static function get_include_path_list()
	{
		$list = [COREPATH];

		// パッケージのロード
		foreach(glob(PKGPATH . '*', GLOB_ONLYDIR) as $dir){
			$list[] = $dir . '/';
		}
		$list[] = APPPATH;

		return $list;
	}

	public static function load($file)
	{
		return include $file;
	}

	public static function value($var)
	{
		return ($var instanceof \Closure) ? $var() : $var;
	}

	/**
	 * ランダム文字列を生成する
	 */
	static function make_random_code($length = 32, $char_seed = [])
	{
		if( ! $char_seed ){
			$char_seed = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
		}

		$code = "";
		for($i = 0; $i < $length; $i++){
			$code .= $char_seed[mt_rand(0, count($char_seed) - 1)];
		}

		return $code;
	}

	static function strip_namespace($full_class)
	{
		$pos               = strripos($full_class, '\\');
		$unqualified_class = $pos ? substr($full_class, $pos + 1) : $full_class;

		return $unqualified_class;
	}

	static function uuidv4(): string
	{
		return uuid_create(UUID_TYPE_RANDOM);
	}

	static function url2a($content)
	{
		$regex       =
			'`\bhttps?+:(?://(?:(?:[-.0-9_a-z~]|%[0-9a-f][0-9a-f]' .
			'|[!$&-,:;=])*+@)?+(?:\[(?:(?:[0-9a-f]{1,4}:){6}(?:' .
			'[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:\d|[1-9]\d|1\d{2}|2' .
			'[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25' .
			'[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?' .
			':\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5]))|::(?:[0-9a-f' .
			']{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:\d|[1' .
			'-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{' .
			'2}|2[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\\' .
			'd|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])' .
			')|(?:[0-9a-f]{1,4})?+::(?:[0-9a-f]{1,4}:){4}(?:[0-' .
			'9a-f]{1,4}:[0-9a-f]{1,4}|(?:\d|[1-9]\d|1\d{2}|2[0-' .
			'4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-' .
			'5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?:\d' .
			'|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5]))|(?:(?:[0-9a-f]{' .
			'1,4}:)?+[0-9a-f]{1,4})?+::(?:[0-9a-f]{1,4}:){3}(?:' .
			'[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:\d|[1-9]\d|1\d{2}|2' .
			'[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25' .
			'[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?' .
			':\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5]))|(?:(?:[0-9a-' .
			'f]{1,4}:){0,2}[0-9a-f]{1,4})?+::(?:[0-9a-f]{1,4}:)' .
			'{2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:\d|[1-9]\d|1\\' .
			'd{2}|2[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4' .
			']\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5' .
			'])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5]))|(?:(?:' .
			'[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?+::[0-9a-f]{1,4' .
			'}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:\d|[1-9]\d|1\d' .
			'{2}|2[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]' .
			'\d|25[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5]' .
			')\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5]))|(?:(?:[' .
			'0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?+::(?:[0-9a-f]{1' .
			',4}:[0-9a-f]{1,4}|(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25' .
			'[0-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?' .
			':\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?:\d|[1-9]\\' .
			'd|1\d{2}|2[0-4]\d|25[0-5]))|(?:(?:[0-9a-f]{1,4}:){' .
			'0,5}[0-9a-f]{1,4})?+::[0-9a-f]{1,4}|(?:(?:[0-9a-f]' .
			'{1,4}:){0,6}[0-9a-f]{1,4})?+::|v[0-9a-f]++\.[!$&-.' .
			'0-;=_a-z~]++)\]|(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0' .
			'-5])\.(?:\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?:\\' .
			'd|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\.(?:\d|[1-9]\d|' .
			'1\d{2}|2[0-4]\d|25[0-5])|(?:[-.0-9_a-z~]|%[0-9a-f]' .
			'[0-9a-f]|[!$&-,;=])*+)(?::\d*+)?+(?:/(?:[-.0-9_a-z' .
			'~]|%[0-9a-f][0-9a-f]|[!$&-,:;=@])*+)*+|/(?:(?:[-.0' .
			'-9_a-z~]|%[0-9a-f][0-9a-f]|[!$&-,:;=@])++(?:/(?:[-' .
			'.0-9_a-z~]|%[0-9a-f][0-9a-f]|[!$&-,:;=@])*+)*+)?+|' .
			'(?:[-.0-9_a-z~]|%[0-9a-f][0-9a-f]|[!$&-,:;=@])++(?' .
			':/(?:[-.0-9_a-z~]|%[0-9a-f][0-9a-f]|[!$&-,:;=@])*+' .
			')*+)?+(?:\?+(?:[-.0-9_a-z~]|%[0-9a-f][0-9a-f]|[!$&' .
			'-,/:;=?+@])*+)?+(?:#(?:[-.0-9_a-z~]|%[0-9a-f][0-9a' .
			'-f]|[!$&-,/:;=?+@])*+)?+`i';
		$replacement = '<a target="_blank" href="$0">$0</a>';
		$content     = preg_replace($regex, $replacement, $content);

		return $content;
	}
}
