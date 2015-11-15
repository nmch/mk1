<?php

/**
 * ログドライバ : ファイル
 */
class Log_File implements Logic_Interface_Log_Driver
{
	private $fp;

	function __construct($config)
	{
		$path = Arr::get($config, 'path');
		if( ! file_exists($path) ){
			if( mkdir($path, 0777, true) === false ){
				throw new MkException('cannot make log directory');
			}
		}
		$path = realpath($path);
		if( ! $path ){
			throw new MkException('cannot resolve log directory');
		}
		$filename_pattern = Arr::get($config, 'filename', 'Ymd');
		$fileext          = Arr::get($config, 'fileext', 'log');
		$filename         = date($filename_pattern) . '.' . $fileext;
		$this->fp         = fopen($path . '/' . $filename, 'at');
		if( $this->fp === false ){
			throw new MkException('cannot open log file');
		}
	}

	function __destruct()
	{
		fclose($this->fp);
	}

	function write($data)
	{
		$str = empty($data['str']) ? '' : $data['str'];
		fwrite($this->fp, rtrim($str) . "\n");
	}
}
