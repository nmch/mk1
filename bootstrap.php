<?php

ini_set('display_errors', 0);
mb_internal_encoding('UTF-8');

class MkException extends Exception
{
	public $log_level = 'ERROR';
}

class AppException extends MkException
{
}

class DatabaseQueryError extends MkException
{
}

class RecordNotFoundException extends MkException
{
}

class HttpNotFoundException extends MkException
{
}

class UnauthorizedException extends MkException
{
}

class BadRequestException extends MkException
{
}

class RedirectException extends MkException
{
}

class InvalidCsrfTokenException extends MkException
{
}

class ValidateErrorException extends MkException
{
	protected static $af;
	
	public function set_af(Actionform $af)
	{
		static::$af = $af;
	}
	
	public function get_af(): Actionform
	{
		return static::$af ?: Actionform::instance();
	}
}

class ImageErrorException extends MkException
{
}

set_error_handler(function($errno, $errstr, $errfile, $errline){
	//echo "errno=$errno / ".error_reporting()."<HR>";
	if( error_reporting() & $errno ){    // ←ここを無効にするとSmartyが新規にコンパイルした中間コードを保存する際にエラーが起きる
		throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
		exit;
	}
}
);
set_exception_handler(function($e){
	ErrorHandler::exception_handler($e);
	exit;
}
);
register_shutdown_function(function(){
	ErrorHandler::shutdown_handler();
}
);

defined('MK_START_TIME') or define('MK_START_TIME', microtime(true));
defined('MK_START_MEM') or define('MK_START_MEM', memory_get_usage());
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// ユニットテストモードフラグ
defined('UNITTESTMODE') or define('UNITTESTMODE', isset($_SERVER['UNITTESTMODE']));

// 各種パスを設定
if( empty($_SERVER['SCRIPT_FILENAME']) ){
	throw new MkException('empty SCRIPT_FILENAME');
}
define('FWNAME', 'mk1');
define('FWPATH', __DIR__ . '/');
define('COREPATH', realpath(FWPATH . 'core/') . '/');
define('SCRIPTPATH', realpath($_SERVER['SCRIPT_FILENAME']));
define('SCRIPTDIR', dirname(realpath(SCRIPTPATH)) . '/');
if( UNITTESTMODE ){    //ユニットテストモード
	define('PROJECTPATH', realpath(COREPATH . '../../') . '/');
}
else{
	if( file_exists(SCRIPTDIR . FWNAME) ){
		define('PROJECTPATH', SCRIPTDIR);    // CLIの場合
	}
	else{
		define('PROJECTPATH', realpath(SCRIPTDIR . '../') . '/');
	}
}
define('APPPATH', realpath(PROJECTPATH . 'app/') . '/');
define('PKGPATH', realpath(PROJECTPATH . 'packages/') . '/');

set_include_path(get_include_path()
                 . PATH_SEPARATOR . APPPATH . 'plugin'
                 . PATH_SEPARATOR . APPPATH . 'vendor'
                 . PATH_SEPARATOR . COREPATH . 'plugin'
                 . PATH_SEPARATOR . FWPATH . 'vendor'
);

// オートローダー + コアbootstrap
require COREPATH . 'classes/autoloader.php';
require COREPATH . 'bootstrap.php';
if( file_exists(APPPATH . 'bootstrap.php') ){
	require APPPATH . 'bootstrap.php';
}
Autoloader::register();

$core_composer_autoloader = FWPATH . 'vendor/autoload.php';
if( is_file($core_composer_autoloader) ){
	include $core_composer_autoloader;
}

$composer_autoloader = PROJECTPATH . 'vendor/autoload.php';
if( is_file($composer_autoloader) ){
	include $composer_autoloader;
}

$retval = 0;
try {
	// 実行環境
	$mk = Mk::instance();
} catch(Exception $e){
	if( Mk::is_cli() ){
		$retval = 1;
	}
	else{
		if( $e instanceof UnauthorizedException ){
			http_response_code(403);
		}
		elseif( $e instanceof BadRequestException ){
			http_response_code($e->getCode() ?: 400);
		}
		else{
			http_response_code(500);
		}
	}
	throw $e;
}

// Sentry初期化
Sentry::instance();

// ユニットテストモード
if( Mk::is_unittesting() ){
	/*
	echo "FWNAME=".FWNAME."\n";
	echo "FWPATH=".FWPATH."\n";
	echo "COREPATH=".COREPATH."\n";
	echo "SCRIPTPATH=".SCRIPTPATH."\n";
	echo "SCRIPTDIR=".SCRIPTDIR."\n";
	echo "PROJECTPATH=".PROJECTPATH."\n";
	echo "APPPATH=".APPPATH."\n";
	echo "PKGPATH=".PKGPATH."\n";
	*/
	if( ! isset($_SERVER['UNITTESTMODE_WITHOUT_MIGRATION']) ){
		// DB初期化
		DB::delete_all_tables();
		// マイグレーション実行
		Task_Coretask::refine('migration');
	}
	
	return;
}

// リクエストURIがある場合は URI → ルーター → コントローラーを実行
// ない場合はモジュール名が決まっているので、Task_NAME を実行
if( Mk::is_cli() ){
	$argv = $_SERVER['argv'];
	// CLIで実行された場合
	if( empty($_SERVER['argc']) || $_SERVER['argc'] < 2 ){
		//echo "usage: {$argv[0]} TASK_NAME [options...]\n";
		echo "usage: {$argv[0]} COMMAND [options...]\n";
		exit;
	}
	if( ! method_exists('Task_Coretask', $argv[1]) ){
		throw new MkException('unknown command');
	}
	$retval = forward_static_call_array(['Task_Coretask', $argv[1]], array_slice($argv, 2));
}
else{
	$request_uri_from_server = parse_url(Arr::get($_SERVER, 'REDIRECT_URL') ?: Arr::get($_SERVER, 'REQUEST_URI'), PHP_URL_PATH);
	if( ! $request_uri_from_server ){
		$uri = '/';
	}
	else{
		$uri = $request_uri_from_server;
	}
	$request_method = Arr::get($_SERVER, 'REQUEST_METHOD');
	Log::coredebug("[REQUEST] $request_method $uri");
	
	ErrorHandler::add_error_handler(function($e){
		$af = Actionform::instance();
		$af->set('error', $e);
		$uri         = explode('/', Config::get('routes._500_', 'default/500'));
		$request_500 = new Request($uri, Request::METHOD_GET);
		$request_500->exception($e);
		$request_500->execute();
	}
	);
	
	$request = new Request($uri, $request_method);
	try {
		$request->execute();
	} catch(RedirectException $e){
		http_response_code($e->getCode() ?: 302);
		header('Location: ' . $e->getMessage());
	} catch(BadRequestException $e){
		$uri         = explode('/', Config::get('routes._400_', 'default/400'));
		$request_400 = new Request($uri, Request::METHOD_GET);
		$request_400->prev_request($request);
		$request_400->exception($e);
		$request_400->execute();
	} catch(UnauthorizedException $e){
		$uri         = explode('/', Config::get('routes._403_', 'default/403'));
		$request_403 = new Request($uri, Request::METHOD_GET);
		$request_403->prev_request($request);
		$request_403->exception($e);
		$request_403->execute();
	} catch(HttpNotFoundException $e){
		$uri         = explode('/', Config::get('routes._404_', 'default/404'));
		$request_404 = new Request($uri, Request::METHOD_GET);
		$request_404->prev_request($request);
		$request_404->exception($e);
		$request_404->execute();
	}
}

exit($retval);
