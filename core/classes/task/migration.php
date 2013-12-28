<?
class Task_Migration extends Task
{
	function run()
	{
		$last_seq = 0;
		$table_existing_check = DB::query("select * from information_schema.tables where table_schema='public' and table_name='migrations'")->execute();
		if($table_existing_check->count() == 0){
			DB::query("create table migrations ( last_seq integer )")->execute();
			DB::query("insert into migrations (last_seq) values (0)")->execute();
			echo "migrationsテーブルを作成しました\n";
		}
		else{
			$last_seq = DB::query("select last_seq from migrations")->execute()->get('last_seq');
			//echo "last_seq=$last_seq\n";
		}
		
		$migration_files = glob(PROJECTPATH.'migration/[0-9][0-9][0-9]_*');
		if($migration_files){
			sort($migration_files);
			foreach($migration_files as $migration_file){
				list($seq,$name) = explode('_',pathinfo($migration_file,PATHINFO_BASENAME),2);
				if(strlen($seq) != 3)
					continue;
				$seq = (int)$seq;
				
				if($seq <= $last_seq){
					echo "[skip $seq] ";
					continue;
				}
				echo "\n$seq : $name ... ";
				
				$query = file_get_contents($migration_file);
				DB::start_transaction();
				try{
					$r = DB::query($query)->execute();
					Log::coredebug($query,$r);
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
	}
}
class Model_Migration extends Model
{
}
