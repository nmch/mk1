<?
ini_set('display_errors', 0);

class MkException extends Exception {}
class AppException extends MkException {}
class DatabaseQueryError extends MkException {}
class RecordNotFoundException extends MkException {}
class HttpNotFoundException extends MkException {}
class ValidateErrorException extends MkException {}

set_error_handler(function($errno, $errstr, $errfile, $errline ){
	//echo "errno=$errno / ".error_reporting()."<HR>";
	if(error_reporting() & $errno){	// ←ここを無効にするとSmartyが新規にコンパイルした中間コードを保存する際にエラーが起きる
		throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
		exit;
	}
});
set_exception_handler(function($e){
	Error::exception_handler($e);
	exit;
});
register_shutdown_function(function(){
	Error::shutdown_handler();
});

defined('MK_START_TIME') or define('MK_START_TIME', microtime(true));
defined('MK_START_MEM') or define('MK_START_MEM', memory_get_usage());
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// 各種パスを設定
if(empty($_SERVER['SCRIPT_FILENAME']))
	throw new MkException('empty SCRIPT_FILENAME');
define('FWNAME',      'mk1');
define('FWPATH',      __DIR__.'/');
define('COREPATH',    realpath(FWPATH.'core/').'/');
define('SCRIPTPATH',  realpath($_SERVER['SCRIPT_FILENAME']));
define('SCRIPTDIR',   dirname(realpath(SCRIPTPATH)).'/');
if(file_exists(SCRIPTDIR.FWNAME))
	define('PROJECTPATH', SCRIPTDIR);	// CLIの場合
else
	define('PROJECTPATH', realpath(SCRIPTDIR.'../').'/');
define('APPPATH',     realpath(PROJECTPATH.'app/').'/');
define('PKGPATH',     realpath(PROJECTPATH.'packages/').'/');

set_include_path(get_include_path()
	. PATH_SEPARATOR . APPPATH  . 'plugin'
	. PATH_SEPARATOR . APPPATH  . 'vendor'
	. PATH_SEPARATOR . COREPATH . 'plugin'
	. PATH_SEPARATOR . FWPATH   . 'vendor'
);

// オートローダー + コアbootstrap
require COREPATH.'classes/autoloader.php';
require COREPATH.'bootstrap.php';
Autoloader::register();

// 実行環境
$mk = Mk::instance();

// リクエストURIがある場合は URI → ルーター → コントローラーを実行
// ない場合はモジュール名が決まっているので、Task_NAME を実行
$retval = 0;
if( ! empty($_SERVER['argv']) ){
	$argv = $_SERVER['argv'];
	// CLIで実行された場合
	if(empty($_SERVER['argc']) || $_SERVER['argc'] < 2){
		//echo "usage: {$argv[0]} TASK_NAME [options...]\n";
		echo "usage: {$argv[0]} COMMAND [options...]\n";
		exit;
	}
	if( ! method_exists('Task_Coretask',$argv[1]) )
		throw new MkException('unknown command');
	$retval = forward_static_call_array(array('Task_Coretask',$argv[1]),array_slice($argv,2));
}
else{
	if( empty($_SERVER['REDIRECT_URL']) )
		$uri = '/';
	else
		$uri = $_SERVER['REDIRECT_URL'];
	Log::coredebug("[bootstrap] REQUEST_URI=$uri");
	
	Error::add_error_handler(function($e){
		$af = Actionform::instance();
		$af->set('error',$e);
		$uri = explode('/',Config::get('routes._500_','default/500'));
		$request_500 = new Request($uri);
		$request_500->execute();
	});
	
	try {
		$request = new Request($uri);
		$request->execute();
	} catch(HttpNotFoundException $e){
		$uri = explode('/',Config::get('routes._404_','default/404'));
		$request_404 = new Request($uri);
		$request_404->execute();
	}
}

exit($retval);

/*
$mk = new Mk;

list($controller,$controller_method_name,$controller_method_options) = $mk->get_controller();

$controller_return_var = call_user_func_array(array($controller,$controller_method_name),$controller_method_options);
*/

//echo class_exists('Controller_Test2');

