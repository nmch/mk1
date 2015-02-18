<?php

/**
 * データベースマイグレーション
 */
class Task_Migration extends Task
{
	function run()
	{
		$argv = func_get_args();

		// renumberオプション
		if( in_array('renumber', $argv) ){
			$seq = 10;
			foreach($this->get_migration_files() as $file){
				$new_filename = sprintf('%04d_%s', $seq, $file['name']);
				echo "[{$file['seq']} -> $seq] ";
				rename($file['dirname'] . '/' . $file['basename'], $file['dirname'] . '/' . $new_filename);

				$seq += 10;
			}

			// renumber時はinitオプションを自動追加
			array_push($argv, 'init');
		}

		// initオプション
		if( in_array('init', $argv) ){
			DB::delete_all_tables();
		}

		$last_seq             = 0;
		$table_existing_check = DB::query("SELECT * FROM information_schema.tables WHERE table_schema='public' AND table_name='migrations'")->execute();
		if( $table_existing_check->count() == 0 ){
			DB::query("CREATE TABLE migrations ( last_seq INTEGER )")->execute();
			DB::query("INSERT INTO migrations (last_seq) VALUES (0)")->execute();
			echo "migrationsテーブルを作成しました\n";
		}
		else{
			$last_seq = DB::query("SELECT last_seq FROM migrations")->execute()->get('last_seq');
			//echo "last_seq=$last_seq\n";
		}

		foreach($this->get_migration_files() as $migration_file){
			$seq  = $migration_file['seq'];
			$name = $migration_file['name'];

			if( $seq <= $last_seq ){
				echo "[skip $seq] ";
				continue;
			}
			printf("\n%4d : %s -> ", $seq, $name);

			$query = file_get_contents($migration_file['dirname'] . '/' . $migration_file['basename']);
			DB::start_transaction();
			try {
				$r = DB::query($query)->execute();
				//Log::coredebug($query,$r);
				DB::query("update migrations set last_seq=$seq")->execute();
				DB::commit_transaction();
				Log::info("[db migration] seq=$seq / $name");
				echo "OK";
			} catch(Exception $e){
				DB::rollback_transaction();
				//Log::coredebug($e->getMessage());
				//print_r( $e->getTrace());
				echo "Error";
				break;
			}
		}
		echo "\n";

		DB::clear_schema_cache();
	}

	function get_migration_files()
	{
		$migration_files = glob(PROJECTPATH . 'migration/[0-9]*_*');
		if( $migration_files === false ){
			throw new MkException('マイグレーションファイルの取得に失敗しました');
		}

		$data = [];
		foreach($migration_files as $migration_file){
			$pathinfo = pathinfo($migration_file);
			list($seq, $name) = explode('_', $pathinfo['basename'], 2);
			if( ! is_numeric($seq) ){
				throw new MkException("シーケンス [{$seq}] は数値で指定して下さい");
				continue;
			}
			$seq        = intval($seq);
			$data[$seq] = $pathinfo + [
					'seq'  => $seq,
					'name' => $name,
				];
		}
		ksort($data);

		return $data;
	}
}

class Model_Migration extends Model
{
}
