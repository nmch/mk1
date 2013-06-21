<?
class Mk
{
	use Singleton;
	
	const DEVELOPMENT	= 'development';
	const TEST			= 'test';
	const PRODUCTION	= 'production';
	const STAGE			= 'stage';
	public static $env = Mk::DEVELOPMENT;
	
	public $config;
	public static $include_path_list;
	public static $session;
	
	function __construct()
	{
		// 実行環境の決定
		self::$env = self::DEVELOPMENT;
		if( ! empty($_SERVER['FUEL_ENV']) )
			self::$env = $_SERVER['FUEL_ENV'];
		if( ! empty($_SERVER['MK_ENV']) )
			self::$env = $_SERVER['MK_ENV'];
		
		self::$include_path_list = self::get_include_path_list();
		$this->config = Config::instance();
		Log::coredebug("[mk] env=".self::$env);
		if(self::$env == self::PRODUCTION){
			error_reporting(0);
			ini_set('display_errors', 0);
		}
		
		$locale = setlocale(LC_ALL,Config::get('locale','en_US'));
		//Log::debug("locale=$locale");
		
		if(Config::get('session.auto_initialize') && php_sapi_name() != 'cli')
			self::$session = Session::instance();
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
}
