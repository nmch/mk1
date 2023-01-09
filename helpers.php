<?php

if( ! function_exists('blank') ){
	/**
	 * Determine if the given value is "blank".
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	function blank($value)
	{
		if( is_null($value) ){
			return true;
		}
		
		if( is_string($value) ){
			return trim($value) === '';
		}
		
		if( is_numeric($value) || is_bool($value) ){
			return false;
		}
		
		if( $value instanceof Countable ){
			return count($value) === 0;
		}
		
		return empty($value);
	}
}

if( ! function_exists('filled') ){
	/**
	 * Determine if a value is "filled".
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	function filled($value)
	{
		return ! blank($value);
	}
}

if( ! function_exists('optional') ){
	/**
	 * Provide access to optional objects.
	 *
	 * @param mixed    $value
	 * @param callable $callback
	 *
	 * @return mixed
	 */
	function optional($value, callable $callback)
	{
		if( ! is_null($value) ){
			return $callback($value);
		}
	}
}

if( ! function_exists('throw_if') ){
	/**
	 * Throw the given exception if the given condition is true.
	 *
	 * @param mixed             $condition
	 * @param \Throwable|string $exception
	 * @param mixed             ...$parameters
	 *
	 * @return mixed
	 *
	 * @throws \Throwable
	 */
	function throw_if($condition, $exception = 'RuntimeException', ...$parameters)
	{
		if( $condition ){
			if( is_string($exception) && class_exists($exception) ){
				$exception = new $exception(...$parameters);
			}
			
			throw is_string($exception) ? new RuntimeException($exception) : $exception;
		}
		
		return $condition;
	}
}

if( ! function_exists('throw_unless') ){
	/**
	 * Throw the given exception unless the given condition is true.
	 *
	 * @param mixed             $condition
	 * @param \Throwable|string $exception
	 * @param mixed             ...$parameters
	 *
	 * @return mixed
	 *
	 * @throws \Throwable
	 */
	function throw_unless($condition, $exception = 'RuntimeException', ...$parameters)
	{
		throw_if(! $condition, $exception, ...$parameters);
		
		return $condition;
	}
}

if( ! function_exists('transform') ){
	/**
	 * Transform the given value if it is present.
	 *
	 * @param mixed    $value
	 * @param callable $callback
	 * @param mixed    $default
	 *
	 * @return mixed|null
	 */
	function transform($value, callable $callback, $default = null)
	{
		if( filled($value) ){
			return $callback($value);
		}
		
		if( is_callable($default) ){
			return $default($value);
		}
		
		return $default;
	}
}

if( ! function_exists('with') ){
	/**
	 * Return the given value, optionally passed through the given callback.
	 *
	 * @template TValue
	 *
	 * @param TValue                          $value
	 * @param (callable(TValue): TValue)|null $callback
	 *
	 * @return TValue
	 */
	function with($value, callable $callback = null)
	{
		return is_null($callback) ? $value : $callback($value);
	}
}
