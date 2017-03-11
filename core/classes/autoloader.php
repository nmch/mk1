<?php

define('DEBUG', 1);

if( DEBUG ){
	require_once COREPATH . 'classes/singleton.php';
	require_once COREPATH . 'classes/config.php';
	require_once COREPATH . 'classes/arr.php';
	require_once COREPATH . 'classes/error_handler.php';
	require_once COREPATH . 'classes/mk.php';
	require_once COREPATH . 'classes/logic/interface/log_driver.php';
	require_once COREPATH . 'classes/log.php';
	require_once COREPATH . 'classes/log/file.php';
}

class Autoloader
{
	protected static $core_namespaces = [];
	
	public static function register()
	{
		spl_autoload_register('Autoloader::load', true, true);
	}
	
	public static function add_core_namespace($namespace, $prefix = true)
	{
		if( $prefix ){
			array_unshift(static::$core_namespaces, $namespace);
		}
		else{
			static::$core_namespaces[] = $namespace;
		}
	}
	
	public static function load($full_class)
	{
		if( class_exists($full_class, false) ){
			return true;
		}
		if( DEBUG ){
			Log::coredebug("[Autoloader::load] {$full_class}");
		}
		
		// deal with funny is_callable('static::classname') side-effect
		if( strpos($full_class, 'static::') === 0 ){
			// is called from within the class, so it's already loaded
			return true;
		}
		
		$full_class        = ltrim($full_class, '\\');
		$pos               = strripos($full_class, '\\');
		$full_ns           = substr($full_class, 0, $pos);
		$unqualified_class = $pos ? substr($full_class, $pos + 1) : $full_class;
		$class             = $unqualified_class;
		
		if( DEBUG ){
			Log::coredebug("[Autoloader::load] try load {$class} ({$full_ns}::{$unqualified_class}) (pos={$pos})");
			//echo "try load {$class} ({$full_ns}::{$unqualified_class}) (pos={$pos})<BR>\n";
		}
		
		$filepath_candidates = [];
		$class_exploded      = explode('_', ltrim(preg_replace_callback('/(_?[A-Z]+[a-z]*)/', function ($splitted){
				$str = $splitted[0];
				if( $str[0] != '_' ){
					$str = '_' . $str;
				}
				
				return strtolower($str);
			}, $class
			), '_'
			)
		);
		if( DEBUG ){
			Log::coredebug('$class_exploded = ' . print_r($class_exploded, true));
		}
		foreach($class_exploded as $key => $value){
			$path                  = $key > 0 ? implode('/', array_slice($class_exploded, 0, $key, true)) : '';
			$basename              = implode('_', array_slice($class_exploded, $key, count($class_exploded), true));
			$filepath_candidates[] = [$path, $basename];
		}
		//echo "filepath_candidates = " . print_r($filepath_candidates, true);
		
		// View::__construct()でも同じロジックを使っているので注意。いつか共通化する。
		if( $full_ns ){
			$additional_path = str_replace('\\', '/', strtolower($full_ns)) . '/';
			//echo "additional_path = {$additional_path}\n";
			$list = [
				PKGPATH . $additional_path,
			];
		}
		else{
			$list = [COREPATH];
			foreach(glob(PKGPATH . '*', GLOB_ONLYDIR) as $dir){
				$list[] = $dir . '/';
			}
			$list[] = APPPATH;
		}
		$include_path_list = array_reverse($list);
		if( DEBUG ){
			Log::coredebug('$include_path_list = ' . print_r($include_path_list, true));
		}
		
		foreach($include_path_list as $include_path){
			$include_path .= 'classes/';
			
			foreach($filepath_candidates as $filepath_candidate){
				$path     = $filepath_candidate[0];
				$basename = $filepath_candidate[1];
				
				if( $path ){
					$path .= '/';
				}
				
				$filepath = $include_path . $path . $basename . '.php';
				if( DEBUG ){
					Log::coredebug("check {$filepath}");
					//echo "check $filepath <br>\n";
				}
				if( is_readable($filepath) ){
					if( DEBUG ){
						Log::coredebug("found! {$filepath}");
						//echo "found! $filepath<BR>\n";
					}
					include($filepath);
					if( class_exists($full_class) && method_exists($full_class, '_init') && is_callable($full_class . '::_init') ){
						call_user_func($full_class . '::_init');
					}
					
					/**
					 * リクエストされたクラスがネームスペース無しだった場合で
					 * コアネームスペースとして定義されているネームスペース内に
					 * 同じクラス名が存在した場合は、コアネームスペース内のクラスを
					 * グローバルネームスペースにaliasする
					 */
					if( ! $full_ns && ! class_exists($full_class, false) ){
						foreach(static::$core_namespaces as $ns){
							if( DEBUG ){
								Log::coredebug("コアNS探索: {$ns}");
							}
							if( class_exists("{$ns}\\{$class}", false) || trait_exists("{$ns}\\{$class}", false) ){
								class_alias("{$ns}\\{$class}", $class);
								if( DEBUG ){
									Log::coredebug("Class Alias: {$ns}\\{$class} to {$class}");
								}
							}
						}
					}
					
					if( class_exists($full_class) || interface_exists($full_class) ){
						return true;
					}
				}
			}
		}
		
		return false;
	}
}
