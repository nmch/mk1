<?php
/**
 * Part of the mk1 framework.
 *
 * @method static Cache instance()
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Cache
{
	use Singleton;
	
	protected Cache_Driver $cache_driver;
	protected array        $cache_config;
	
	function __construct()
	{
		$driver_name = Config::get('cache.driver');
		
		$driver_class_name = ("Cache_Driver_" . ucfirst($driver_name));
		if( ! class_exists($driver_class_name) ){
			throw new MkException("キャッシュドライバ {$driver_name} がみつかりません");
		}
		
		$global_config         = Config::get("cache.global_config", []);
		$driver_default_config = Config::get("cache.driver_config.{$driver_name}", []);
		$this->cache_config    = array_merge($global_config, $driver_default_config);
		
		$this->cache_driver = new $driver_class_name($this->cache_config);
		if( ! $this->cache_driver instanceof Cache_Driver ){
			throw new MkException("不正なキャッシュドライバです");
		}
	}
	
	function driver(): \Cache_Driver
	{
		return $this->cache_driver;
	}
	
	function log_on_exception(string $message, Exception $e)
	{
		if( $log_level = Arr::get($this->cache_config, 'log_on_exception') ){
			Log::log($log_level, $message, $e);
		}
	}
	
	function throw_on_exception(Exception $e)
	{
		if( Arr::get($this->cache_config, 'throw_on_exception') ){
			throw $e;
		}
	}
	
	public static function get(string $key, ?string $group = null, ?callable $retrieve_handler = null)
	{
		$cache  = Cache::instance();
		$driver = $cache->driver();
		
		$data = null;
		
		try {
			$data = $driver->get($key, $group);
		} catch(CacheMissException $e){
			if( is_callable($retrieve_handler) ){
				$data = call_user_func_array($retrieve_handler, [$key, $group]);
				static::set($key, $group, $data);
			}
		} catch(Exception $e){
			$cache->log_on_exception("キャッシュ取得中にエラーが発生しました", $e);
			$cache->throw_on_exception($e);
		}
		
		return $data;
	}
	
	public static function clear($key = null, $group = null)
	{
		$cache  = Cache::instance();
		$driver = $cache->driver();
		
		try {
			$driver->clear($key, $group);
		} catch(Exception $e){
			$cache->log_on_exception("キャッシュクリア中にエラーが発生しました(key={$key} / group={$group})", $e);
			$cache->throw_on_exception($e);
		}
	}
	
	public static function clear_group(string $group)
	{
		static::clear(null, $group);
	}
	
	public static function set_with_ttl(string $key, ?string $group, $value, ?int $ttl = null)
	{
		$cache  = Cache::instance();
		$driver = $cache->driver();
		
		try {
			$driver->set($key, $value, $group, $ttl);
		} catch(Exception $e){
			$cache->log_on_exception("キャッシュ保存中にエラーが発生しました", $e);
			$cache->throw_on_exception($e);
		}
		
		return $value;
	}
	
	public static function set(string $key, ?string $group, ...$options)
	{
		// group未指定の場合は$groupを$valueとして扱う
		$value = empty($options) ? $group : ($options[0] ?? null);
		
		return static::set_with_ttl($key, $group, $value);
	}
}
