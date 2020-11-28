<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Cache
{
	public static function get($key, $group = null, $retrieve_handler = null)
	{
		$cache_dir = static::cache_dir($key, $group);
		$filepath  = $cache_dir . static::key($key, $group);
		
		$data = null;
		
		try {
			if( is_callable($retrieve_handler) ){
				$data = call_user_func_array($retrieve_handler, [$key, $group]);
				static::set($key, $group, $data);
			}
			else{
				if( file_exists($filepath) ){
					//Log::coredebug("[cache] hit $key($group)");
					$expire = Config::get('cache.default_expire');
					if( $expire ){
						$filemtime = filemtime($filepath);
						$live_time = (time() - $filemtime);
						if( $live_time < $expire ){
							$data = unserialize(file_get_contents($filepath));
						}
						else{
							unlink($filepath);
						}
					}
				}
			}
		} catch(Exception $e){
			Log::error("キャッシュ取得中にエラーが発生しました", $e);
			$data = null;
		}
		
		return $data;
	}
	
	public static function clear($key = null, $group = null)
	{
		$cache_dir = static::cache_dir($key, $group);
		$filepath  = $cache_dir . static::key($key, $group);
		Log::coredebug("[Cache]: キャッシュクリア({$key}/{$group}", $filepath);
		try {
			File::rm($filepath);
		} catch(Exception $e){
			// 削除できなくてもエラーにはしない
		}
	}
	
	public static function clear_group($group)
	{
		$cache_dir = static::cache_dir(null, $group);
		try {
			if( is_dir($cache_dir) ){
				File::rm($cache_dir);
			}
		} catch(Exception $e){
			// 削除できなくてもエラーにはしない
		}
	}
	
	public static function cache_dir($key, $group = null)
	{
		$cache_dir = Config::get('cache.cache_dir');
		if( ! $cache_dir ){
			throw new Exception('invalid cache dir');
		}
		if( $group ){
			$cache_dir .= sha1($group) . '/';
		}
		if( $key ){
			// ここでkeyが必須ではないのは、migration時等にディレクトリだけ特定して全部消す用途でも使われるから。
			$cache_dir .= substr(sha1($key), 0, 3) . '/';
		}
		
		return $cache_dir;
	}
	
	protected static function key($key, $group = null)
	{
		if( ! $key ){
			throw new Exception('invalid key');
		}
		$key = sha1($key);
		
		if( $group ){
			$key = $group . '_' . $key;
		}
		
		return $key;
	}
	
	public static function set()
	{
		$args = func_get_args();
		if( count($args) == 2 ){
			$key   = $args[0];
			$group = null;
			$value = $args[1];
		}
		else{
			if( count($args) == 3 ){
				$key   = $args[0];
				$group = $args[1];
				$value = $args[2];
			}
			else{
				throw new Exception('invalid parameters');
			}
		}
		
		$cache_dir = static::cache_dir($key, $group);
		if( ! file_exists($cache_dir) ){
			try {
				$r = mkdir($cache_dir, 0777, true);
				if( $r === false ){
					throw new Exception();
				}
			} catch(Exception $e){
				throw new Exception("cannot make cache dir [{$cache_dir}]", $e->getCode(), $e);
			}
		}
		
		$filepath = $cache_dir . static::key($key, $group);
		$r        = file_put_contents($filepath, serialize($value));
		if( $r === false ){
			throw new Exception('cannot write cache file');
		}
	}
}