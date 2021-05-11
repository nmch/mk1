<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Response_File extends Response
{
	const CONVERT_ENCODING_METHOD_ONMEMORY = 'onmemory';
	
	protected $filepath;
	protected $filebody;
	protected $filename;
	protected $do_not_unlink;
	protected $unlink_targets = [];
	protected $mime_type      = 'application/octet-stream';
	
	function __construct($filepath, $filename, $do_not_unlink = false, array $headers = [])
	{
		Log::coredebug("[response file] filename=$filename");
		
		$this->headers       = $headers;
		$this->filename      = $filename;
		$this->do_not_unlink = $do_not_unlink;
		
		if( $filepath ){
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
			$this->filepath = $filepath;
			$this->filebody = file_get_contents($this->filepath);
			if( ! $this->do_not_unlink ){
				$this->unlink_targets[] = $filepath;
			}
		}
	}
	
	function get_filename()
	{
		return $this->filename;
	}
	
	function get_filebody()
	{
		return $this->filebody;
	}
	
	function set_filebody($filebody)
	{
		$this->filebody = $filebody;
		
		return $this;
	}
	
	function convert_encoding($to, $from)
	{
		$this->filebody = mb_convert_encoding($this->filebody, $to, $from);
		
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
			$encoded_filename = rawurlencode($this->filename);
			$this->set_header('Content-Type', $this->mime_type);
			$this->set_header('Content-Disposition', 'attachment; filename*=UTF-8\'\'' . $encoded_filename);
			$this->set_header('Content-Transfer-Encoding', 'binary');
			$this->set_header('Content-Length', strlen($this->filebody));
			
			$this->send_header();
			
			echo $this->filebody;
			
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
