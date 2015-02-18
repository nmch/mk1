<?php

class Task_Coretask extends Task
{
	public static function job($cmd = 'run')
	{
		switch($cmd){
			case 'run':
				return static::_job_run();
			case 'daemon':
				return static::_job_daemon();
			default:
				throw new MkException('invalid cmd');
		}
	}

	public static function _job_run()
	{
		/*
				register_shutdown_function(function($job_id){
					$error = error_get_last();
					if($error['message']){
						$error_desc = empty($error['message']) ? '' : $error['message'];
						\DB::update('jobs')->set([
							'job_result_status' => 'fail',
							'job_done_at' => 'now()',
							'job_result_code' => -2,
							'job_log' => "unexpected shutdown [$error_desc]",
						])->where('job_id',$job_id)->execute();
					}
				}, $job->job_id);
		*/
		try {
			$job = Job::fetch();
			if( $job instanceof Job && $job->job_id ){
				Log::info("[job] Job {$job->job_id} fetched by " . gethostname() . '(PID:' . getmypid() . ')');

				if( ! $job->job_task_name || ! $job->job_method_name ){
					throw new Exception('invalid task');
				}

				$cmd = $job->job_task_name . ':' . $job->job_method_name;

				if( $job->job_timeout ){
					set_time_limit($job->job_timeout);
				}

				Task_Coretask::refine($cmd, [$job]);
				$job->success();
				Log::info("[job] Job {$job->job_id} was succeeded");
			}
			else{
				return 1;
			}
		} catch(Exception $e){
			if( DB::in_transaction() ){
				DB::rollback_transaction();
			}
			if( isset($job) ){
				$job->fail(-1, $e->getFile() . ':' . $e->getLine() . ':' . $e->getCode() . ':' . $e->getMessage());
				Log::error("[job] Job {$job->job_id} was FAILED");
			}
		}
	}

	public static function refine()
	{
		Error::add_error_handler(['Task_Coretask', 'refine_error']);

		$args = func_get_args();
		if( count($args) < 1 ){
			throw new MkException('invalid task option');
		}
		$task_name = explode(':', array_shift($args));
		if( empty($task_name[1]) ){
			$task_name[1] = 'run';
		}
		$class_name  = $task_name[0];
		$method_name = $task_name[1];

		$class_name = 'Task_' . ucfirst($class_name);
		if( ! class_exists($class_name) ){
			throw new MkException("task class {$class_name} not found");
		}
		$task_object = new $class_name;
		if( ! method_exists($task_object, $method_name) ){
			throw new MkException("task method {$method_name} not found");
		}

		Log::coredebug("[task] run {$class_name}->{$method_name}", $args);

		return call_user_func_array([$task_object, $method_name], $args);
	}

	public static function _job_daemon($concurrency = 3)
	{
		$cmd      = PROJECTPATH . "job_runner.sh";
		$downfile = PROJECTPATH . "down";

		Log::info("[job] ジョブ実行デーモン 多重度=$concurrency");
		$procs = [];
		$stats = [];
		while( 1 ){
			for($num = 0; $num < $concurrency; $num++){
				if( ! empty($procs[$num]) ){
					$stats[$num] = proc_get_status($procs[$num]);
				}
				else{
					$stats[$num] = false;
				}

				if( empty($stats[$num]['running']) && ! file_exists($downfile) ){
					$procs[$num] = proc_open($cmd, [], $pipes);
					if( $procs[$num] === false ){
						Log::error('[job] 新しいプロセスの起動に失敗しました');
					}
					else{
						Log::info('[job] 新しいプロセスを起動しました');
						usleep(500 * 1000);
					}
				}
			}
			sleep(1);
		}
	}

	public static function refine_error($error)
	{
		echo "[Error]---\n";
		if( $error instanceof Exception ){
			$error = (string)$error;
		}
		if( is_array($error) ){
			$error = print_r($error, true);
		}
		echo $error . "\n";
	}

	public static function init()
	{
		if( func_num_args() == 0 ){
			echo "プロジェクト名を指定して下さい\n";
			exit;
		}
		$name = func_get_arg(0);
		echo "プロジェクト $name を作成します\n";
		if( file_exists($name) ){
			echo "ディレクトリ $name はすでに存在しています\n";
			exit;
		}
		if( mkdir($name) === false ){
			echo "ディレクトリ $name が作成できませんでした\n";
			exit;
		}
		if( chdir($name) === false ){
			echo "ディレクトリ $name に移動出来ません\n";
			exit;
		}
		/*
		if(symlink(FWPATH, FWNAME) === false){
			echo "フレームワーク ".FWPATH." のシンボリックリンクが作成できませんでした\n";
			exit;
		}
		*/

		// スケルトンのコピー
		$cmd = "cp -ar " . FWPATH . "skel/* ./";
		echo $cmd . "\n";
		passthru($cmd);
		$cmd = "cp -ar " . FWPATH . "skel/.git* ./";
		echo $cmd . "\n";
		passthru($cmd);

		// Git 初期化
		passthru("git init");
		// フレームワークをサブモジュールとして追加
		passthru("git submodule add git@rm.hkkd.info:mk1.git");
		passthru("git submodule update --init");
		/*
		// mkコマンドのリンク
		if(symlink('./mk1/mk.php', 'mk') === false){
			echo "mkコマンドのシンボリックリンクが作成できませんでした\n";
			exit;
		}
		*/
	}
}
