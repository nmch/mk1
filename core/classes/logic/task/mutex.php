<?php

trait Logic_Task_Mutex
{
	function mutex($timeout = 3600)
	{
		$filepath = $this->get_pid_filepath();
		if( file_exists($filepath) ){
			$mtime = filemtime($filepath);
			Log::coredebug("pid file exists ({$filepath})/mtime=" . date(DATE_ATOM, $mtime));
			if( (time() - $mtime) > $timeout ){
				$this->create_pidfile();
			}
			else{
				return false;
			}
		}
		else{
			$this->create_pidfile();
		}
		
		return true;
	}
	
	function get_pid_filepath()
	{
		$class_name = get_called_class();
		$pid_dir    = $this->get_pid_dir();
		
		$filename = strtolower("{$class_name}.pid");
		$filepath = "{$pid_dir}/{$filename}";
		
		return $filepath;
	}
	
	function get_pid_dir()
	{
		$pid_dir = Config::get('mutex.pid_dir');
		if( ! file_exists($pid_dir) ){
			mkdir($pid_dir, 0755, true);
		}
		
		return $pid_dir;
	}
	
	function drop_pidfile()
	{
		$filepath = $this->get_pid_filepath();
		if( file_exists($filepath) ){
			unlink($filepath);
			Log::coredebug("pid file deleted ({$filepath})");
		}
	}
	
	function update_pidfile()
	{
		$this->create_pidfile();
	}
	
	function create_pidfile()
	{
		$filepath = $this->get_pid_filepath();
		
		$pid = getmypid();
		file_put_contents($filepath, $pid);
		
		Log::coredebug("wrote pid file ({$filepath}) pid={$pid}");
	}
}