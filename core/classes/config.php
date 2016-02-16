<?php

class Config
{
	use Singleton;
	static $config;

	function __construct()
	{
		self::$config = [];
		foreach(Mk::$include_path_list as $dir){
			self::$config = Arr::merge(self::$config, $this->load_all($dir . 'config/'));
		}
	}

	function load_all($dir)
	{
		$dir = realpath($dir) . '/';
		if( ! is_dir($dir) ){
			return [];
		}

		$config = [];
		$files  = glob($dir . '*.php');
		if( Mk::$env ){
			$files = array_merge(glob($dir . '*.php'), glob($dir . Mk::$env . '/*.php'));
		}
		foreach($files as $file){
			if( ! is_file($file) || ! is_readable($file) ){
				continue;
			}

			$r = $this->load($file);
			if( ! is_array($r) ){
				$r = [];
			}
			$group = pathinfo($file, PATHINFO_FILENAME);
			if( $group === 'config' ){
				$config = Arr::merge($config, $r);
			}
			else{
				Arr::set($config, $group, Arr::merge(Arr::get($config, $group, []), $r));
			}
			unset($r);
		}
		unset($files);

		return $config;
	}

	function load($filename)
	{
		return Mk::load($filename);
	}

	static function get($key, $default = null)
	{
		return Arr::get(self::$config, $key, $default);
	}

	static function set($key, $value)
	{
		return Arr::set(self::$config, $key, $value);
	}
}
