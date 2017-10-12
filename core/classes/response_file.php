<?php

class Response_File extends Response
{
	const CONVERT_ENCODING_METHOD_ONMEMORY = 'onmemory';
	
	protected $filepath;
	protected $filename;
	protected $do_not_unlink;
	protected $unlink_targets = [];
	protected $mime_type      = 'application/octet-stream';
	
	function __construct($filepath, $filename, $do_not_unlink = false, array $headers = [])
	{
		Log::coredebug("[response file] filename=$filename");
		
		if( ! file_exists($filepath) ){
			//			throw new Exception("file not found");
			throw new HttpNotFoundException;
		}
		if( ! is_file($filepath) ){
			throw new Exception("specified path is not a regular file");
		}
		if( ! is_readable($filepath) ){
			throw new Exception("cannot read file");
		}
		
		$this->filepath         = $filepath;
		$this->headers          = $headers;
		$this->filename         = $filename;
		$this->do_not_unlink    = $do_not_unlink;
		$this->unlink_targets[] = $filepath;
	}
	
	function convert_encoding($to, $from, $method = 'onmemory')
	{
		$tmpfile = tempnam(sys_get_temp_dir(), 'CNV');
		file_put_contents($tmpfile, mb_convert_encoding(file_get_contents($this->filepath), $to, $from));
		$this->unlink_targets[] = $tmpfile;
		$this->filepath         = $tmpfile;
		
		return $this;
	}
	
	function set_mime_type($mime_type): Response_File
	{
		$this->mime_type = $mime_type;
		
		return $this;
	}
	
	function send()
	{
		if( $this->before() ){
			header('Content-Type: ' . $this->mime_type);
			$enc = '=?utf-8?B?' . base64_encode($this->filename) . '?=';
			header('Content-Disposition: attachment; filename="' . $enc . '"');
			//header('Content-Disposition: attachment; filename=' . $this->filename);
			header('Content-Transfer-Encoding: binary');
			header('Content-Length: ' . filesize($this->filepath));
			readfile($this->filepath);
			
			if( ! $this->do_not_unlink ){
				foreach($this->unlink_targets as $target){
					if( is_writable($target) ){
						unlink($target);
					}
				}
			}
		}
	}
}
