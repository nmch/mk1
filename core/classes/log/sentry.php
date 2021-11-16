<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Log_Sentry implements Logic_Interface_Log_Driver
{
	private        $config;
	private Sentry $sentry;
	
	function __construct($config)
	{
		$this->config = $config;
		$this->sentry = Sentry::instance();
	}
	
	function __destruct()
	{
	}
	
	protected $log_level_to_sentry_level_map = [
		\Log::LEVEL_COREDEBUG => \Sentry\Severity::DEBUG,
		\Log::LEVEL_DEBUG     => \Sentry\Severity::DEBUG,
		\Log::LEVEL_DEBUG5    => \Sentry\Severity::DEBUG,
		\Log::LEVEL_DEBUG4    => \Sentry\Severity::DEBUG,
		\Log::LEVEL_DEBUG3    => \Sentry\Severity::DEBUG,
		\Log::LEVEL_DEBUG2    => \Sentry\Severity::DEBUG,
		\Log::LEVEL_DEBUG1    => \Sentry\Severity::DEBUG,
		\Log::LEVEL_INFO      => \Sentry\Severity::INFO,
		\Log::LEVEL_NOTICE    => \Sentry\Severity::WARNING,
		\Log::LEVEL_WARNING   => \Sentry\Severity::WARNING,
		\Log::LEVEL_ERROR     => \Sentry\Severity::ERROR,
		\Log::LEVEL_CRITICAL  => \Sentry\Severity::FATAL,
		\Log::LEVEL_ALERT     => \Sentry\Severity::FATAL,
		\Log::LEVEL_EMERGENCY => \Sentry\Severity::FATAL,
	];
	
	function write($data)
	{
		if( ! is_array($data) ){
			throw new MkException("invalid data");
		}
		if( ! Arr::is_assoc($data) ){
			throw new MkException("Sentryログ記録にはaggregateされたデータが必要です");
		}
		
		$log_level           = $data['level'];
		$sentry_level_string = Arr::get($this->log_level_to_sentry_level_map, $log_level, \Sentry\Severity::ERROR);
		$sentry_level        = new \Sentry\Severity($sentry_level_string);
		
		$title             = null;
		$exception         = null;
		$sentry_extra_data = [
			'timestamp_unixtime' => Arr::get($data, 'timestamp_unixtime'),
			'timestamp_string'   => Arr::get($data, 'timestamp_string'),
			'level'              => Arr::get($data, 'level'),
			'level_num'          => Arr::get($data, 'level_num'),
			'uniqid'             => Arr::get($data, 'config.uniqid'),
			'messages'           => Arr::get($data, 'messages'),
		];
		foreach($sentry_extra_data['messages'] as &$message_bag){
			$message = $message_bag['message'];
			if( is_scalar($message) && is_null($title) ){
				$title = $message;
			}
			if( is_object($message) ){
				$message_bag['message'] = get_class($message);
			}
		}
		
		$sentry_data = [
			'level'     => $sentry_level,
			'extra'     => $sentry_extra_data,
			'exception' => $exception,
		];
		$this->sentry->message($title, $sentry_data);
	}
}
