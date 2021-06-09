<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Migration
{
	protected $db_definition_name = null;
	/** @var Database_Connection */
	protected $connection;
	protected $dbname;
	protected $schema;
	
	function __construct()
	{
	}
	
	function set_db_connection($db_definition_name = null)
	{
		$this->db_definition_name = $db_definition_name;
		$this->connection         = DB::get_database_connection($db_definition_name);
		$this->dbname             = $this->connection->get_current_database_name();
		$this->schema             = Database_Schema::get(null, null, true, $db_definition_name);
		
		return $this;
	}
	
	/**
	 * グループごとに最終のマイグレーションを取得する
	 *
	 * @return array
	 * @throws DatabaseQueryError
	 * @throws MkException
	 */
	function get_latest_migrations()
	{
		return DB::select()
		         ->from('migrations')
		         ->execute($this->connection)
		         ->as_array(false, 'migration_group')
			;
	}
	
	function search_migration_files(array $search_path_list)
	{
		$data = [];
		foreach($search_path_list as $search_path){
			if( ! file_exists($search_path) || ! is_dir($search_path) ){
				continue;
			}
			//Log::coredebug("migration: search_path={$search_path}");
			$migration_files = glob($search_path . '[0-9]*_*');
			//Log::coredebug("migration: migration_files", $migration_files);
			if( $migration_files === false ){
				throw new MkException('マイグレーションファイルの取得に失敗しました');
			}
			
			foreach($migration_files as $migration_file){
				//Log::coredebug("migration: migration_file={$migration_file}");
				$pathinfo = pathinfo($migration_file);
				$basename = $pathinfo['filename'];
				Log::coredebug("migration: basename={$basename}");
				if( preg_match('#^([0-9]+)(-([^_]+))?_([^.]*)\.?(.*)?$#', $basename, $match) ){
					//Log::coredebug("migration: migration filename match", $match);
					$seq   = intval(Arr::get($match, 1));
					$group = Arr::get($match, 3);
					if( strlen($group) === 0 ){
						$group = 'NOGROUP'; // グループなしの場合の表記"NOGROUP"を変更するときは、既存DBのマイグレーションが必要なので注意!!
					}
					
					$pathinfo['seq']   = $seq;
					$pathinfo['group'] = $group;
					$pathinfo['name']  = Arr::get($match, 4);
					$pathinfo['db']    = Arr::get($match, 5);
					
					$data[$seq][$group] = $pathinfo;
				}
			}
		}
		ksort($data);
		Log::coredebug("migration: targets", $data);
		
		return $data;
	}
	
	/**
	 * migrationsテーブルのバージョンアップ
	 */
	function migration_migrations_table()
	{
		$schema = Arr::get($this->schema, 'migrations');
		
		/**
		 * migrationsテーブルのバージョンアップ
		 */
		{
			$migrations_migration_1_to_2         = false;
			$migrations_migration_1_to_2_lastseq = null;
			if( $schema && count($schema['columns']) === 1 ){
				$migrations_migration_1_to_2         = true;
				$migrations_migration_1_to_2_lastseq = DB::query("SELECT last_seq FROM migrations")->execute($this->connection)->get('last_seq');
				Log::coredebug("[db migration] migrationsテーブルのバージョンアップ(1 -> 2)を行います。現在のシーケンスは[{$migrations_migration_1_to_2_lastseq}]です。");
				DB::query("DROP TABLE migrations")->execute($this->connection);
				$schema = null;
			}
		}
		
		if( ! $schema ){
			$q = <<<SQL
CREATE TABLE migrations (
	migration_group TEXT PRIMARY KEY ,
	migration_last_seq INTEGER NOT NULL DEFAULT 0
);
INSERT INTO migrations (migration_group) VALUES ('NOGROUP');
SQL;
			DB::query($q)->execute($this->connection);
			Log::coredebug("[db migration] migrationsテーブルを作成しました");
		}
		
		/**
		 * migrationsテーブルのバージョンアップ
		 */
		if( $migrations_migration_1_to_2 ){
			DB::update('migrations')->set([
				'migration_last_seq' => $migrations_migration_1_to_2_lastseq,
			])->where('migration_group', 'NOGROUP')->execute($this->connection)
			;
			Log::coredebug("[db migration] migrationsテーブルのバージョンアップ(1 -> 2)が完了しました。");
		}
		
		return $this;
	}
	
	protected $last_seq_list;
	
	/**
	 * 指定グループの最終マイグレーションを取得する
	 */
	function get_last_seq($group)
	{
		if( ! $this->last_seq_list ){
			$this->last_seq_list = $this->get_latest_migrations();
		}
		
		return Arr::get($this->last_seq_list, $group);
	}
	
	function clear_last_seq_cache()
	{
		$this->last_seq_list = null;
	}
	
	function clear_schema_cache()
	{
		DB::clear_schema_cache();
	}
	
	const MIGRATION_SKIPPED = 'SKIPPED';
	
	/**
	 * マイグレーションファイルの実行
	 *
	 * @param array    $migration_file
	 * @param callable $message_handler
	 */
	function execute_migration_file($migration_file, $message_handler = null)
	{
		$group = $migration_file['group'];
		$seq   = $migration_file['seq'];
		$name  = $migration_file['name'];
		
		$last_seq_item = $this->get_last_seq($group);
		$last_seq      = 0;
		if( ! $last_seq_item ){
			DB::insert('migrations')->values(['migration_group' => $group])->execute();
			$this->clear_last_seq_cache();
		}
		else{
			$last_seq = $last_seq_item['migration_last_seq'];
		}
		
		$result = null;
		
		if( $seq <= $last_seq ){
			Log::coredebug("[db migration] マイグレーションをスキップしました / group={$group} / seq={$seq}");
			$result = Migration::MIGRATION_SKIPPED;
			if( is_callable($message_handler) ){
				$message_handler(".");
			}
		}
		else{
			if( is_callable($message_handler) ){
				$message_handler(sprintf("\n%10s : %4d : %s -> ", $group, $seq, $name));
			}
			
			$filepath = ($migration_file['dirname'] . '/' . $migration_file['basename']);
			$query    = file_get_contents($filepath);
			
			$execute_connection = Database_Connection::instance($migration_file['db'] ?? null);
			
			DB::start_transaction($execute_connection);
			try {
				if( Arr::get($migration_file, 'extension') === 'task' ){
					/**
					 * タスク実行
					 */
					$task_target      = explode(':', trim($query));
					$task_class_name  = $task_target[0];
					$task_method_name = isset($task_target[1]) ? $task_target[1] : 'run';
					if( ! class_exists($task_class_name) ){
						throw new MkException("タスク {$task_class_name} がみつかりません");
					}
					/** @var Task $task_class */
					$task_class = new $task_class_name;
					if( ! ($task_class instanceof Task) ){
						throw new MkException("{$task_class_name} が実行できません");
					}
					if( ! method_exists($task_class, $task_method_name) ){
						throw new MkException("{$task_class_name}:{$task_method_name} が実行できません");
					}
					$task_class->set_execute_connection($execute_connection);
					
					// migrationしたデータをModelで変更する場合はスキーマキャッシュを削除しなければいけない
					$this->clear_schema_cache();
					
					call_user_func([$task_class, $task_method_name]);
				}
				elseif( Arr::get($migration_file, 'extension') === 'php' ){
					/**
					 * PHPコード実行
					 */
					$func = include($filepath);
					call_user_func($func);
				}
				else{
					/**
					 * SQL実行
					 */
					DB::query($query)->execute($execute_connection);
				}
				
				DB::commit_transaction($execute_connection);
				
				DB::update("migrations")->set(['migration_last_seq' => $seq])->where('migration_group', $group)->execute($this->connection);
				Log::coredebug("[db migration] マイグレーションの実行に成功しました / group={$group} / seq={$seq} / {$name}");
				if( is_callable($message_handler) ){
					$message_handler("OK");
				}
			} catch(Exception $e){
				DB::rollback_transaction($execute_connection);
				//Log::coredebug($e->getMessage());
				//print_r( $e->getTrace());
				$this->error = [
					'group'     => $group,
					'seq'       => $seq,
					'name'      => $name,
					'exception' => $e,
				];
				
				Log::error("[db migration] マイグレーション失敗 / group={$group} / seq={$seq} / {$name}", $e);
				if( is_callable($message_handler) ){
					$msg = "Error\n{$e->getMessage()}";
					$message_handler($msg);
				}
				
				throw $e;
			}
		}
		
		return $result;
	}
}
