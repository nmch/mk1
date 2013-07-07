<?
class Cache
{
	protected static function cache_dir()
	{
		$cache_dir = Config::get('cache.cache_dir');
		if( ! $cache_dir  )
			throw new Exception('invalid cache dir');
		if( ! file_exists($cache_dir) ){
			$r = mkdir($cache_dir,0777,true);
			if( $r === false )
				throw new Exception('cannot make cache dir');
		}
		
		return $cache_dir;
	}
	protected static function key($key,$group = NULL)
	{
		if( ! $key )
			throw new Exception('invalid key');
		if($group)
			$key = $group.'_'.$key;
		return $key;
	}
	public static function get($key,$group = NULL)
	{
		$cache_dir = static::cache_dir();
		$filepath = $cache_dir . static::key($key,$group);
		
		if(file_exists($filepath)){
			//Log::coredebug("[cache] hit $key($group)");
			return unserialize(file_get_contents($filepath));
		}
		else
			return NULL;
	}
	public static function set()
	{
		$args = func_get_args();
		if(count($args) == 2){
			$key   = $args[0];
			$group = NULL;
			$value = $args[1];
		}
		else if(count($args) == 3){
			$key   = $args[0];
			$group = $args[1];
			$value = $args[2];
		}
		else
			throw new Exception('invalid parameters');
			
		$cache_dir = static::cache_dir();
		$filepath = $cache_dir . static::key($key,$group);
		
		$r = file_put_contents($filepath,serialize($value));
		if($r === false)
			throw new Exception('cannot write cache file');
	}
}