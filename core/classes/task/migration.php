<?php

/**
 * データベースマイグレーション
 */
class Task_Migration extends Task
{
	function run()
	{
		Log::coredebug("[db migration] データベースマイグレーションを実行します");
		DB::clear_schema_cache();
		
		$argv = func_get_args();
		
		// renumberオプション
		if( in_array('renumber', $argv) ){
			Log::coredebug("[db migration] リナンバリングを実行します");
			
			$seq = 10;
			foreach($this->get_migration_files() as $groups){
				foreach($groups as $file){
					$new_filename = sprintf('%04d', $seq);
					$group        = Arr::get($file, 'group');
					if( $group !== 'NOGROUP' ){
						$new_filename .= "-{$group}";
					}
					$new_filename .= "_{$file['name']}";
					if( strlen($file['extension']) ){
						$new_filename .= '.' . $file['extension'];
					}
					
					Log::coredebug("[db migration] [Renumber] {$group}/{$file['seq']} -> [$seq]");
					echo "[Renumber] {$group}/{$file['seq']} -> [$seq]\n";
					
					rename($file['dirname'] . '/' . $file['basename'], $file['dirname'] . '/' . $new_filename);
					
					$seq += 10;
				}
			}
			
			// renumber時はinitオプションを自動追加
			array_push($argv, 'init');
		}
		
		// initオプション
		if( in_array('init', $argv) ){
			Log::coredebug("[db migration] すべてのテーブルを削除します");
			DB::delete_all_tables();
		}
		
		$schema = Database_Schema::get('migrations');
		
		/**
		 * migrationsテーブルのバージョンアップ
		 */
		{
			$migrations_migration_1_to_2         = false;
			$migrations_migration_1_to_2_lastseq = null;
			if( $schema && count($schema['columns']) === 1 ){
				$migrations_migration_1_to_2         = true;
				$migrations_migration_1_to_2_lastseq = DB::query("SELECT last_seq FROM migrations")->execute()->get('last_seq');
				Log::coredebug("[db migration] migrationsテーブルのバージョンアップ(1 -> 2)を行います。現在のシーケンスは[{$migrations_migration_1_to_2_lastseq}]です。");
				DB::query("DROP TABLE migrations")->execute();
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
			DB::query($q)->execute();
			Log::coredebug("[db migration] migrationsテーブルを作成しました");
		}
		
		/**
		 * migrationsテーブルのバージョンアップ
		 */
		if( $migrations_migration_1_to_2 ){
			DB::update('migrations')->set([
				'migration_last_seq' => $migrations_migration_1_to_2_lastseq,
			])->where('migration_group', 'NOGROUP')->execute()
			;
			Log::coredebug("[db migration] migrationsテーブルのバージョンアップ(1 -> 2)が完了しました。");
		}
		
		$last_seq_list = $this->get_latest_migrations();
		
		foreach($this->get_migration_files() as $groups){
			foreach($groups as $group => $migration_file){
				$seq  = $migration_file['seq'];
				$name = $migration_file['name'];
				
				$last_seq_item = Arr::get($last_seq_list, $group);
				$last_seq      = 0;
				if( ! $last_seq_item ){
					DB::insert('migrations')->values(['migration_group' => $group])->execute();
					$last_seq_list = $this->get_latest_migrations();
				}
				else{
					$last_seq = $last_seq_item['migration_last_seq'];
				}
				
				if( $seq <= $last_seq ){
					Log::coredebug("[db migration] マイグレーションをスキップしました / group={$group} / seq={$seq}");
					echo ".";
				}
				else{
					printf("\n%10s : %4d : %s -> ", $group, $seq, $name);
					
					$filepath = ($migration_file['dirname'] . '/' . $migration_file['basename']);
					$query    = file_get_contents($filepath);
					
					DB::start_transaction();
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
							
							// migrationしたデータをModelで変更する場合はスキーマキャッシュを削除しなければいけない
							DB::clear_schema_cache();
							
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
							$r = DB::query($query)->execute($migration_file['db'] ?? null);
							//Log::coredebug($query,$r);
						}
						
						DB::update("migrations")->set(['migration_last_seq' => $seq])->where('migration_group', $group)->execute();
						DB::commit_transaction();
						Log::coredebug("[db migration] マイグレーションの実行に成功しました / group={$group} / seq={$seq} / {$name}");
						echo "OK";
					} catch(Exception $e){
						DB::rollback_transaction();
						//Log::coredebug($e->getMessage());
						//print_r( $e->getTrace());
						Log::error("[db migration] マイグレーション失敗 / group={$group} / seq={$seq} / {$name}", $e);
						
						$msg = "Error\n{$e->getMessage()}";
						echo $msg;
						break 2;
					}
				}
			}
		}
		echo "\n";
		
		DB::clear_schema_cache();
	}
	
	function get_latest_migrations()
	{
		return DB::select()->from('migrations')->execute()->as_array(false, 'migration_group');
	}
	
	function get_migration_files()
	{
		$search_path_list = [
			PROJECTPATH . 'migration/',
		];
		foreach(Mk::package_directories() as $dir){
			$search_path_list[] = ($dir . '/migration/');
		}
		
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
					
					if( ! empty($data[$seq][$group]) ){
						throw new MkException("グループ[{$group}]のシーケンス[{$seq}]の定義が重複しています");
					}
					
					$data[$seq][$group] = $pathinfo;
				}
			}
		}
		ksort($data);
		Log::coredebug("migration: targets", $data);
		
		return $data;
	}
}

class Model_Migration extends Model
{
}
