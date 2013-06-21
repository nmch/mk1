<?
class Config
{
	use Singleton;
	static $config;
	
	function __construct()
	{
		self::$config = array();
		foreach(Mk::$include_path_list as $dir){
			self::$config = Arr::merge(self::$config,$this->load_all($dir.'config/'));
		}
	}
	static function get($key,$default = NULL)
	{
		return Arr::get(self::$config,$key,$default);
	}
	static function set($key,$value)
	{
		return Arr::set(self::$config,$key,$value);
	}
	function load_all($dir)
	{
		$dir = realpath($dir).'/';
		if( ! is_dir($dir) )
			return array();
		
		$config = array();
		$files = glob($dir.'*.php');
		if(Mk::$env)
			$files = array_merge(glob($dir.'*.php'),glob($dir.Mk::$env.'/*.php'));
		foreach($files as $file){
			if( ! is_file($file) || ! is_readable($file) )
				continue;
			
			$r = $this->load($file);
			if( ! is_array($r) )
				$r = array();
			$group = pathinfo($file, PATHINFO_FILENAME);
			if($group && $group != 'config')
				$r = array($group => $r);
			$config = Arr::merge($config,$r);
			unset($r);
		}
		unset($files);
		
		return $config;
	}
	function load($filename){
		return Mk::load($filename);
	}
}
