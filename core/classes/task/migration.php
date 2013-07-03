<?
class Task_Migration extends Task
{
	function run()
	{
		
		
		$migration_files = glob(PROJECTPATH.'migration/[0-9][0-9][0-9]_*');
		if($migration_files){
			sort($migration_files);
			foreach($migration_files as $migration_file){
				list($seq,$name) = explode('_',pathinfo($migration_file,PATHINFO_BASENAME),2);
				if(strlen($seq) != 3)
					continue;
				$seq = (int)$seq;
				echo "$seq : $name\n";
			}
		}
	}
}
class Model_Migration extends Model
{
}
