<?php

class Session_Driver_Db extends Session_Driver
{
	function gc($maxlifetime)
	{
		$database = Arr::get($this->config, "database");
		
		if( $maxlifetime > 0 ){
			$maxlifetime = intval($maxlifetime);
			DB::delete()
			  ->from($this->config['table'])
			  ->where('age(updated_at)', '>', "{$maxlifetime} sec")
			  ->execute($database)
			;
		}
		
		return true;
	}
	
	function destroy($id)
	{
		$database = Arr::get($this->config, "database");
		
		DB::delete()
		  ->from($this->config['table'])
		  ->where('id', $id)
		  ->execute($database)
		;
		
		return true;
	}
	
	function write($id, $data)
	{
		$database = Arr::get($this->config, "database");
		list($encoded_data, $hash) = $this->encode_data($data);
		
		Log::suppress();
		DB::insert($this->config['table'])
		  ->values([
				  'id'         => $id,
				  'data'       => $encoded_data,
				  'hash'       => $hash,
				  'updated_at' => 'now()',
			  ]
		  )
		  ->on_conflict(['id'])
		  ->execute($database)
		;
		Log::unsuppress();
		
		return true;
	}
	
	function read($id)
	{
		$data = null;
		
		$database = Arr::get($this->config, "database");
		$r        = DB::select()->from($this->config['table'])->where('id', $id)->execute($database);
		if( $r->count() ){
			$record = $r->get();
			
			$data = $this->decode_data(Arr::get($record, 'data'), Arr::get($record, 'hash'));
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
		$database   = Arr::get($this->config, "database");
		$schema     = Database_Schema::get($table_name, null, null, $database);
		
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
			DB::query($q)->execute($database);
			\Database_Schema::clear_cache();
			$schema = Database_Schema::get($table_name);
		}
		
		$schema_modified = false;
		if( ! Arr::get($schema, 'columns.created_at') ){
			$q = <<<SQL
ALTER TABLE sessions ADD created_at TIMESTAMP DEFAULT now();
UPDATE sessions SET created_at=updated_at;
SQL;
			DB::query($q)->execute($database);
			$schema_modified = true;
		}
		if( ! Arr::get($schema, 'columns.hash') ){
			$q = <<<SQL
ALTER TABLE sessions ADD hash TEXT;
SQL;
			DB::query($q)->execute($database);
			$schema_modified = true;
		}
		if( $schema_modified ){
			\Database_Schema::clear_cache();
		}
		
		return true;
	}
}