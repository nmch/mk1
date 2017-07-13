<?php

class File
{
	protected $filepath;
	protected $filename;
	protected $mime;
	
	function __construct($filepath, $filename = null, $mime = null)
	{
		if( ! file_exists($filepath) ){
			throw new MkException("file not found");
		}
		$this->filepath = $filepath;
		$this->filename = $filename ?: basename($filepath);
		$this->mime     = $mime ?: "application/octet-stream";
	}
	
	/**
	 * 再帰的にファイルまたはディレクトリを削除
	 */
	static function rm($filepath)
	{
		$filepath = rtrim($filepath, '/');
		//Log::coredebug("File::rm : $filepath");
		if( is_dir($filepath) ){
			$objects = scandir($filepath);
			foreach($objects as $object){
				if( $object == "." || $object == ".." ){
					continue;
				}
				
				static::rm($filepath . "/" . $object);
			}
			unset($objects);
			$r = rmdir($filepath);
			if( $r !== true ){
				throw new MkException("rmdir {$filepath} failed");
			}
		}
		else{
			$r = unlink($filepath);
			if( $r !== true ){
				throw new MkException("unlink {$filepath} failed");
			}
		}
	}
}
