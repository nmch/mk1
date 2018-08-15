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
		$encoded_data = base64_encode($data);
		$hash         = md5($encoded_data);
		
		DB::insert($this->config['table'])
		  ->values([
				  'id'         => $id,
				  'data'       => $encoded_data,
				  'hash'       => $hash,
				  'updated_at' => 'now()',
			  ]
		  )
		  ->on_conflict(['id'])
		  ->execute()
		;
		
		return true;
	}
	
	function read($id)
	{
		$data = null;
		
		$r = DB::select()->from($this->config['table'])->where('id', $id)->execute();
		if( $r->count() ){
			$record       = $r->get();
			$hash         = Arr::get($record, 'hash');
			$encoded_data = Arr::get($record, 'data');
			
			if( $hash ){
				$decoded_data = base64_decode($encoded_data);
				if( $encoded_data !== false ){
					$encoded_data_hash = md5($encoded_data);
					if( $encoded_data_hash === $hash ){
						$data = $decoded_data;
					}
				}
			}
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
	data TEXT,
	hash TEXT,
	created_at TIMESTAMP DEFAULT now(),
	updated_at TIMESTAMP DEFAULT now()
);
SQL;
			DB::query($q)->execute();
			\Database_Schema::clear_cache();
			$schema = Database_Schema::get($table_name);
		}
		
		$schema_modified = false;
		if( ! Arr::get($schema, 'columns.created_at') ){
			$q = <<<SQL
alter table sessions add created_at TIMESTAMP DEFAULT now();
update sessions set created_at=updated_at;
SQL;
			DB::query($q)->execute();
			$schema_modified = true;
		}
		if( ! Arr::get($schema, 'columns.hash') ){
			$q = <<<SQL
alter table sessions add hash text;
SQL;
			DB::query($q)->execute();
			$schema_modified = true;
		}
		if( $schema_modified ){
			\Database_Schema::clear_cache();
		}
		
		return true;
	}
}