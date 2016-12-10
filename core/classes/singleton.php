<?php

trait Singleton
{
	static private $instance;

	static function instance($force_new = false)
	{
		if( $force_new || is_null(static::$instance) ){
			static::$instance = new static;
		}

		return static::$instance;
	}
}
