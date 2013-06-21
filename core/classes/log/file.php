<?
class Log_File
{
	private $fp;
	
	function __construct()
	{
		$path = Config::get('log.path');
		if( ! file_exists($path) ){
			if(mkdir($path,0777,true) === false)
				throw new MkException('cannot make log directory');
		}
		$path = realpath($path);
		if( ! $path )
			throw new MkException('cannot resolve log directory');
		$filename = date('Ymd').".log";
		$this->fp = fopen($path.'/'.$filename,'at');
		if($this->fp === false)
			throw new MkException('cannot open log file');
	}
	function __destruct()
	{
		fclose($this->fp);
	}
	
	function write($data)
	{
		$str = empty($data['str']) ? '' : $data['str'];
		fwrite($this->fp,rtrim($str)."\n");
	}
}
