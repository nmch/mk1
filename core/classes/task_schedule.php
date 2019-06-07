<?php

class Task_Schedule
{
	static function update_schema()
	{
		/**
		 * extension作成
		 */
		$uuid_check = DB::select()->from('pg_extension')->where('extname', 'uuid-ossp')->execute();
		if( ! $uuid_check->count() ){
			DB::query('CREATE EXTENSION "uuid-ossp"')->execute();
		}
		
		/**
		 * テーブル作成・更新
		 */
		static::update_schema_task_schedules();
		static::update_schema_task_histories();
	}
	
	private static function update_schema_task_histories(): bool
	{
		$updated = false;
		
		$schema = Database_Schema::get('task_histories', null, true);
		if( ! $schema ){
			$create_sql = <<<SQL
CREATE TABLE task_histories (
  th_unique_code TEXT PRIMARY KEY UNIQUE DEFAULT uuid_generate_v4()::TEXT,
  th_created_at TIMESTAMP DEFAULT clock_timestamp(),
  th_updated_at TIMESTAMP DEFAULT clock_timestamp(),
  th_ts_id INTEGER NOT NULL REFERENCES task_schedules(ts_id) ON DELETE CASCADE ,
  th_scheduled_start_at TIMESTAMP NOT NULL,
  th_started_at TIMESTAMP ,
  th_host TEXT,
  th_pid TEXT,
  th_executing_status TEXT,
  th_executing_status_updated_at TEXT,
  th_terminated_at TIMESTAMP ,
  th_result_message TEXT,
  th_result_code TEXT,
  th_result_data JSONB,
  th_failed BOOLEAN
);
CREATE INDEX ON task_histories(th_ts_id);
CREATE INDEX ON task_histories((th_started_at IS NULL));
CREATE INDEX ON task_histories((th_terminated_at IS NULL));
CREATE INDEX ON task_histories USING brin(th_created_at);
SQL;
			DB::query($create_sql)->execute();
			$updated = true;
		}
		
		return $updated;
	}
	
	private static function update_schema_task_schedules(): bool
	{
		$updated = false;
		
		$schema = Database_Schema::get('task_histories', null, true);
		if( ! $schema ){
			$create_sql = <<<SQL
CREATE TABLE task_schedules (
  ts_id SERIAL PRIMARY KEY ,
  ts_created_at TIMESTAMP DEFAULT clock_timestamp(),
  ts_updated_at TIMESTAMP DEFAULT clock_timestamp(),
  ts_name TEXT NOT NULL UNIQUE ,
  ts_task TEXT NOT NULL,
  ts_method TEXT NOT NULL,
  ts_priority INTEGER DEFAULT 100,
  ts_execute_cycle TEXT,
  ts_execute_interval INTERVAL,
  ts_execute_time TIME,
  ts_timeout_interval INTERVAL,
  ts_keep_history_interval INTERVAL
);
SQL;
			DB::query($create_sql)->execute();
			$updated = true;
		}
		
		return $updated;
	}
	
	static function execute()
	{
		static::schedule();
		
		/** @var Model_Task_History[] $target_th_list */
		$target_th_list = [];
		
		/**
		 * タイムアウトしたthの終了マーク
		 */
		$savepoint = DB::place_savepoint();
		try {
			$targets = Model_Task_History::find()
			                             ->select_for('update')
			                             ->join("join task_schedules on ts_id=th_ts_id")
			                             ->where('age(th_started_at)', '>', DB::expr('ts_timeout_interval'))
			                             ->where('th_started_at', 'is not', null)
			                             ->where('th_terminated_at', null)
			                             ->where('ts_timeout_interval', 'is not', null)
			                             ->execute()
			;
			
			/** @var Model_Task_History $th */
			foreach($targets as $th){
				$th->timeout();
			}
			
			DB::commit_savepoint($savepoint);
		} catch(Exception $e){
			DB::rollback_savepoint($savepoint);
			throw $e;
		}
		
		/**
		 * タスク開始マーク
		 */
		$savepoint = DB::place_savepoint();
		try {
			$targets = Model_Task_History::find()
			                             ->select_for('update')
			                             ->join("join task_schedules on ts_id=th_ts_id")
			                             ->where('th_started_at', null)
			                             ->where('th_scheduled_start_at', '<=', DB::expr('clock_timestamp()'))
			                             ->order_by("ts_priority")
			                             ->order_by("ts_id")
			                             ->execute()
			;
			
			/** @var Model_Task_History $th */
			foreach($targets as $th){
				$th->start();
				$target_th_list[] = $th;
			}
			
			DB::commit_savepoint($savepoint);
		} catch(Exception $e){
			DB::rollback_savepoint($savepoint);
			throw $e;
		}
		
		/**
		 * タスク実行
		 */
		foreach($target_th_list as $th){
			try {
				$task_name = "Task_" . ucfirst($th['ts_task']);
				if( ! class_exists($task_name) ){
					throw new Exception("タスク {$task_name} がみつかりませんでした");
				}
				
				/** @var Task $task */
				$task = new $task_name();
				
				$method_name = $th->ts_method;
				if( ! method_exists($task, $method_name) ){
					throw new Exception("タスク {$task_name} にメソッド {$method_name} がみつかりませんでした");
				}
				
				$task->set_task_history($th);
				call_user_func([$task, $method_name]);
				
			} catch(Exception $e){
				$th->fail($e->getCode(), $e->getMessage());
			}
			$th->terminate();
		}
	}
	
	static function schedule()
	{
		static::update_schema();
		
		/**
		 * 定期タスク
		 */
		$q = <<<SQL
INSERT INTO task_histories
(
	th_updated_at
,   th_ts_id
,   th_scheduled_start_at
)
SELECT
	clock_timestamp()
  , ts_id
  , coalesce(th_scheduled_start_at, clock_timestamp()) + ts_execute_interval
FROM task_schedules
LEFT JOIN (
	SELECT DISTINCT ON (th_ts_id) *
	FROM task_histories
	ORDER BY th_ts_id, th_scheduled_start_at DESC
) AS th ON ts_id = th_ts_id
WHERE ts_execute_cycle = 'cycle'
  AND ts_execute_interval IS NOT NULL
  AND (th_terminated_at IS NOT NULL OR th_ts_id IS NULL);
SQL;
		DB::query($q)->execute();
	}
	
	static function add_schedule($name, $method, array $data = [])
	{
		static::update_schema();
		
		$ts = Model_Task_Schedule::find()
		                         ->where('ts_name', $name)
		                         ->get_one()
		;
		if( ! $ts ){
			$ts          = new Model_Task_Schedule();
			$ts->ts_name = $name;
		}
		
		$ts->ts_updated_at            = DB::expr('clock_timestamp()');
		$ts->ts_task                  = $data['task'] ?? $name;
		$ts->ts_method                = $method ?? 'run';
		$ts->ts_priority              = $data['priority'] ?? 100;
		$ts->ts_execute_cycle         = $data['execute_cycle'] ?? null;
		$ts->ts_execute_interval      = $data['execute_interval'] ?? null;
		$ts->ts_execute_time          = $data['execute_time'] ?? null;
		$ts->ts_timeout_interval      = $data['timeout_interval'] ?? null;
		$ts->ts_keep_history_interval = $data['keep_history_interval'] ?? null;
		$ts->save();
	}
}
