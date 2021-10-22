<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * データベースマイグレーション
 */
class Task_Migration extends Task
{
	protected $silent   = false;
	protected $messages = [];
	protected $error;
	/** @var Migration */
	protected $migration;
	
	function before()
	{
		parent::before();
		
		$this->migration = (new Migration())->set_db_connection();
	}
	
	function run($argv = null)
	{
		Log::coredebug("[db migration] データベースマイグレーションを実行します");
		DB::clear_schema_cache();
		
		if( $argv === null ){
			$argv = func_get_args();
		}
		
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
						$new_filename .= ('.' . $file['extension']);
					}
					
					Log::coredebug("[db migration] [Renumber] {$group}/{$file['seq']} -> [$seq]");
					$this->echo_message("[Renumber] {$group}/{$file['seq']} -> [$seq]\n");
					
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
		
		$this->migration->migration_migrations_table();
		
		foreach($this->get_migration_files() as $groups){
			foreach($groups as $group => $migration_file){
				try {
					$this->migration->execute_migration_file($migration_file, function($message){
						$this->echo_message($message);
					});
				} catch(Exception $e){
					break 2;
				}
			}
		}
		
		$this->echo_message("\n");
		
		DB::clear_schema_cache();
	}
	
	function has_error(): bool
	{
		return boolval($this->error);
	}
	
	function set_silent($silent = null)
	{
		$this->silent = $silent;
		
		return $this;
	}
	
	protected function echo_message($message)
	{
		if( trim($message) ){
			$this->messages[] = trim($message);
		}
		
		if( ! $this->silent ){
			echo $message;
		}
	}
	
	function get_migration_files()
	{
		$search_path_list = [
			PROJECTPATH . 'migration/',
		];
		foreach(Mk::package_directories() as $dir){
			$search_path_list[] = ($dir . '/migration/');
		}
		
		$data = $this->migration->search_migration_files($search_path_list);
		
		return $data;
	}
}

class Model_Migration extends Model
{
}
