<?php
/**
 * Part of the mk1 framework.
 *
 * @method static Sentry instance
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Sentry
{
	use Singleton;
	
	protected bool $initialized = false;
	
	protected \Sentry\Tracing\Transaction $transaction;
	protected \Sentry\Tracing\Span        $app_span;
	
	static function is_enable(): bool
	{
		return boolval(Config::get('sentry.enable'));
	}
	
	static function get_config(): array
	{
		return Config::get('sentry.config');
	}
	
	function __construct()
	{
		if( static::is_enable() ){
			\Sentry\init(static::get_config());
			
			ErrorHandler::add_error_handler(function($e){
				\Sentry\withScope(function(\Sentry\State\Scope $scope) use ($e): void{
					\Sentry\captureException($e);
				});
			});
			
			$this->start_transaction();
			
			$this->initialized = true;
		}
	}
	
	function __destruct()
	{
		$this->terminate();
	}
	
	public function terminate()
	{
		if( static::is_enable() && $this->transaction !== null ){
			if( $this->app_span !== null ){
				$this->app_span->finish();
			}
			
			\Sentry\SentrySdk::getCurrentHub()->setSpan($this->transaction);
			
			$this->transaction->finish();
		}
	}
	
	public function get_app_span(): ?\Sentry\Tracing\Span
	{
		return static::is_enable() ? $this->app_span : null;
	}
	
	public function get_transaction(): ?\Sentry\Tracing\Transaction
	{
		return static::is_enable() ? $this->transaction : null;
	}
	
	public function start_transaction()
	{
		$request_start_time  = Arr::get($_SERVER, 'REQUEST_TIME_FLOAT', microtime(true));
		$sentry_trace_header = Arr::get($_SERVER, 'HTTP_SENTRY_TRACE');
		
		$context = $sentry_trace_header
			? \Sentry\Tracing\TransactionContext::fromSentryTrace($sentry_trace_header)
			: new \Sentry\Tracing\TransactionContext();
		
		if( Mk::is_cli() ){
			$argv = Arr::get($_SERVER, 'argv', []);
			$context->setName(implode(' ', $argv));
			$context->setOp('cli');
			$context->setData([
				'argv' => $argv,
			]);
		}
		else{
			$path = Arr::get($_SERVER, 'PATH_INFO');
			$context->setName($path ?: 'request');
			$context->setOp('http.server');
			$context->setData([
				'url'    => $path,
				'method' => strtoupper(Arr::get($_SERVER, 'REQUEST_METHOD')),
			]);
		}
		$context->setStartTimestamp($request_start_time);
		
		$this->transaction = \Sentry\startTransaction($context);
		
		\Sentry\SentrySdk::getCurrentHub()->setSpan($this->transaction);
		
		$appContextStart = new \Sentry\Tracing\SpanContext();
		$appContextStart->setOp('app.handle');
		$appContextStart->setStartTimestamp(microtime(true));
		
		$this->app_span = $this->transaction->startChild($appContextStart);
		
		\Sentry\SentrySdk::getCurrentHub()->setSpan($this->app_span);
	}
	
	public static function currentTracingSpan(): ?\Sentry\Tracing\Span
	{
		return \Sentry\SentrySdk::getCurrentHub()->getSpan();
	}
	
	public static function span(string $op, callable $function, ?string $description = null, array $data = [])
	{
		$r = null;
		
		if( static::is_enable() && ($current_span = static::currentTracingSpan()) ){
			$context = new \Sentry\Tracing\SpanContext();
			$context->setOp($op);
			$context->setDescription($description);
			$context->setData($data);
			
			$span = $current_span->startChild($context);
			
			\Sentry\withScope(function(\Sentry\State\Scope $scope) use ($span, $function, &$r): void{
				$scope->setSpan($span);
				
				$r = $function($span);
			});
			
			$span->finish();
		}
		else{
			$r = $function();
		}
		
		return $r;
	}
	
	public function message($message, $data = [], $message_level = null)
	{
		if( $this->initialized ){
			\Sentry\withScope(function(\Sentry\State\Scope $scope) use ($message, $data): void{
				$level = Arr::get($data, 'level', $message_level ?? \Sentry\Severity::error());
				$scope->setLevel($level);
				if( $extra = Arr::get($data, 'extra') ){
					$scope->setExtras($extra);
				}
				
				\Sentry\captureMessage($message);
			});
		}
	}
}
