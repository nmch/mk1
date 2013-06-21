<?
trait Singleton {
	static private $instance;
	
	static function instance()
	{
		if (is_null(static::$instance)) {
			static::$instance = new static;
		}
		
		return static::$instance;
	}
}
