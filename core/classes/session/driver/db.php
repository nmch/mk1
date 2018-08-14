<?php

class Session_Driver_Db implements SessionHandlerInterface
{
	private $config;
	private $data;
	
	function __construct($config = [])
	{
		$this->config = $config;
	}
	
	function gc($maxlifetime)
	{
		if( $maxlifetime > 0 ){
			$maxlifetime = intval($maxlifetime);
			DB::delete()->from($this->config['table'])->where('age(updated_at)', '>', "{$maxlifetime} sec")->execute();
		}
		
		return true;
	}
	
	function destroy($id)
	{
		DB::delete()->from($this->config['table'])->where('id', $id)->execute();
		
		return true;
	}
	
	function write($id, $data)
	{
		//Log::coredebug("session write",$id,$data);
		$_check = DB::select()->from($this->config['table'])->where('id', $id)->execute()->count();
		if( $_check ){
			DB::update($this->config['table'])->values([
					'data'       => serialize($data),
					'updated_at' => 'now()',
				]
			)->where('id', $id)->execute()
			;
		}
		else{
			DB::insert($this->config['table'])->values([
					'id'         => $id,
					'data'       => serialize($data),
					'updated_at' => 'now()',
				]
			)->execute()
			;
		}
		
		//Log::coredebug("session wrote");
		return true;
	}
	
	function read($id)
	{
		$data = null;
		$r    = DB::select()->from($this->config['table'])->where('id', $id)->execute();
		if( $r->count() ){
			$data = unserialize($r->get('data'));
		}
		
		//Log::coredebug("session read",$id,$data);
		return strval($data);
	}
	
	function close()
	{
		return true;
	}
	
	function open($savePath, $sessionName)
	{
		$table_name = Arr::get($this->config, "table");
		$schema     = Database_Schema::get($table_name);
		
		if( ! $schema ){
			$q = <<<SQL
CREATE TABLE sessions (
	id TEXT PRIMARY KEY,
	data TEXT ,
	created_at TIMESTAMP DEFAULT now(),
	updated_at TIMESTAMP DEFAULT now()
);
SQL;
			DB::query($q)->execute();
			\Database_Schema::clear_cache();
		}
		
		return true;
	}
}
