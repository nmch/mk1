<?php

/**
 * Class Model_Task_History
 */
class Model_Task_History extends Model
{
	const CODE_TIMEOUT = 'TIMEOUT';
	
	function update($message = null)
	{
		$this->th_executing_status            = $message;
		$this->th_executing_status_updated_at = DB::expr("clock_timestamp()");
		$this->save();
	}
	
	function timeout(): Model_Task_History
	{
		if( ! $this->th_started_at ){
			throw new Exception("開始されていません");
		}
		if( $this->th_terminated_at ){
			throw new Exception("すでに終了しています");
		}
		
		$now = new DateTime();
		
		$this->fail(static::CODE_TIMEOUT, "タスク実行タイムアウト", [
			'ts_timeout_interval' => $this->ts_timeout_interval,
			'now'                 => $now->format(DATE_ISO8601),
		]);
		$this->terminate();
		
		return $this;
	}
	
	function fail($code, $message, ?array $data = null): Model_Task_History
	{
		$this->th_result_code    = $code;
		$this->th_result_message = $message;
		$this->th_result_data    = $data;
		$this->th_failed         = true;
		$this->save();
		
		return $this;
	}
	
	function terminate(): Model_Task_History
	{
		if( $this->th_terminated_at ){
			throw new Exception("すでに終了しています");
		}
		$this->th_terminated_at = DB::expr("clock_timestamp()");
		$this->save();
		
		return $this;
	}
	
	function start(): Model_Task_History
	{
		if( $this->th_started_at ){
			throw new Exception("すでに開始されています");
		}
		$this->th_started_at = DB::expr("clock_timestamp()");
		$this->th_host       = gethostname();
		$this->th_pid        = getmypid();
		$this->save();
		
		return $this;
	}
}
