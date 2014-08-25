<?
class Mk
{
	use Singleton;
	
	const DEVELOPMENT	= 'development';
	const TEST			= 'test';
	const PRODUCTION	= 'production';
	const STAGE			= 'stage';
	public static $env = Mk::PRODUCTION;
	
	public $config;
	public static $include_path_list;
	public static $session;
	
	function __construct()
	{
		// 実行環境の決定
		static::$env = static::PRODUCTION;								// 何も指定されていない場合のデフォルト動作はPRODUCTION
		static::$env = getenv('FUEL_ENV')           ?: static::$env;	// 環境変数
		static::$env = getenv('MK_ENV')             ?: static::$env;	// 環境変数
		static::$env = get_cfg_var('MK_ENV')        ?: static::$env;	// AWS EBSのEC2でCLI実行した場合
		static::$env = Arr::get($_SERVER, 'MK_ENV') ?: static::$env;	// サーバ変数
		// 本番環境以外では実行環境の書き換えに対応
		if( ! static::is_production() ){
			static::$env = Arr::get($_REQUEST, 'MK_ENV') ?: static::$env;
		}
		
		self::$include_path_list = self::get_include_path_list();
		$this->config = Config::instance();
		Log::coredebug("Process Start\n-------------------------------------------------------------------------------------------------------------------");
		Log::coredebug("[mk] env=".self::$env.(self::is_production() ? ' [PRODUCTION]' : ''));
		if(self::is_production()){
			error_reporting(0);
			ini_set('display_errors', 0);
		}
		else{
			error_reporting(E_ALL);
		}
		
		$locale = setlocale(LC_ALL,Config::get('locale','en_US'));
		//Log::debug("locale=$locale");
		
		if(Config::get('session.auto_initialize') && php_sapi_name() != 'cli')
			self::$session = Session::instance();
	}
	
	static function env($env = NULL)
	{
		if($env){
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
	
	// 優先度 低→高の並び
	static function get_include_path_list()
	{
		$list = [COREPATH];
		
		// パッケージのロード
		foreach(glob(PKGPATH.'*',GLOB_ONLYDIR) as $dir){
			$list[] = $dir.'/';
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
			$char_seed = array_merge(range('a','z'),range('A','Z'),range('0','9'));
		}
		
		$code = "";
		for($i = 0;$i < $length;$i++){
			$code .= $char_seed[ mt_rand(0,count($char_seed) - 1) ];
		}
		
		return $code;
	}
}
