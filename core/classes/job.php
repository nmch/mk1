<?php

class Job extends Model
{
	const STATUS_SUCCESS = 'success';
	const STATUS_FAIL    = 'fail';
	
	protected static $_primary_key = ['job_id'];
	
	public function __construct(array $data = [], $new = true, $view = null)
	{
		$r             = parent::__construct($data, $new, $view);
		$this->job_env = Mk::$env;
		
		return $r;
	}
	
	public static function create($task, $method, $options = [])
	{
		$job                  = new static;
		$job->job_task_name   = $task;
		$job->job_method_name = $method;
		foreach($options as $key => $value){
			$job->$key = $value;
		}
		$job->save();
		
		return $job;
	}
	
	static function fetch()
	{
		$hostname = gethostname();
		$pid      = getmypid();
		$job_env  = Mk::$env;
		
		//DB::query('select update_job_ready()')->execute();
		
		$q = "
			with myjob as (
				select job_id from jobs
				where job_env='$job_env'
				and job_ready_at is not null
				and job_fetched_at is null
				order by job_priority,random()
				for update NOWAIT
				limit 1
			)
			update jobs set
				job_fetched_at=now(),
				job_hostname='$hostname',
				job_process_id=$pid,
				job_log='Fetched'
			where job_id in (select job_id from myjob)
			returning *
		";
		try {
			$jobs = DB::query($q)->set_fetch_as(get_called_class())->execute();
			if( count($jobs) ){
				return $jobs->get();
			}
		} catch(DatabaseQueryError $e){
			// ここでのエラーはロック失敗(ロック対象が他のトランザクションによりロックされていた)場合のみを想定して、例外を無視する。
		}
		
		return null;
	}
	
	function unfetch()
	{
		if( $this->job_doing_at || $this->job_done_at ){
			throw new MkException('cannot unfetch doing or done job');
		}
		$this->job_fetched_at = null;
		$this->save();
		
		return $this;
	}
	
	function doing()
	{
		$this->job_doing_at = 'now()';
		$this->save();
		
		return $this;
	}
	
	function success($result_code = 0, $log = '')
	{
		return self::fin(self::STATUS_SUCCESS, $result_code, $log);
	}
	
	function fin($status, $result_code = 0, $log = '')
	{
		if( $status != self::STATUS_SUCCESS && $status != self::STATUS_FAIL ){
			$status = self::STATUS_FAIL;
		}
		
		$this->job_result_code   = $result_code;
		$this->job_result_status = $status;
		$this->job_log           = (string)$log;
		$this->job_done_at       = 'now()';
		$this->save();
		
		if( $status == self::STATUS_SUCCESS ){
			//成功
			if( $this->job_success_autodelete ){
				$q = "
					DELETE FROM jobs
					WHERE job_done_at IS NOT NULL
					AND job_result_status='" . self::STATUS_SUCCESS . "'
					AND job_success_autodelete IS TRUE
					AND job_id NOT IN (SELECT job_wait_id FROM jobs WHERE job_wait_id IS NOT NULL)
					AND (job_seq IS NULL OR job_seq NOT IN (SELECT job_wait_seq FROM jobs WHERE job_wait_seq IS NOT NULL))
				";
				DB::query($q)->execute();
			}
		}
		else{
			//失敗
			//$e = new Exception();
			//Log::coredebug(print_r(DB::query("select * from jobs where job_id={$this->job_id}")->execute()->as_array(),true));
			//Log::coredebug(DB::in_transaction());
			
			//failカウンタアップ
			$this->job_fail_counter++;
			$this->save();
			
			if( $this->job_fail_retry_interval !== null ){
				$this->retry();
			}
		}
		
		return $this;
	}
	
	function retry()
	{
		if( $this->job_fail_retry_max && ($this->job_fail_counter + 1 > $this->job_fail_retry_max) ){
			return;
		}
		
		$this->job_start_at      = date(DATE_ATOM, "+{$this->job_fail_retry_interval} sec"); // "now() + '{$this->job_fail_retry_interval} sec'",
		$this->job_ready_at      = null;
		$this->job_fetched_at    = null;
		$this->job_doing_at      = null;
		$this->job_done_at       = null;
		$this->job_result_status = null;
		$this->job_result_code   = null;
		$this->job_log           = null;
		$this->save();
		
		return $this;
	}
	
	function fail($result_code = 0, $log = '')
	{
		return self::fin(self::STATUS_FAIL, $result_code, $log);
	}
}
