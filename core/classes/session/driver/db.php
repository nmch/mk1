<?
class Session_Driver_Db implements SessionHandlerInterface
{
	private $config;
	private $data;
	
	function __construct($config = [])
	{
		$this->config = $config;
	}
	
	function gc($maxlifetime) {}
	function destroy($id)
	{
		DB::delete()->from($this->config['table'])->where('id',$id)->execute();
	}
	function write($id, $data)
	{
		//Log::coredebug("session write",$id,$data);
		$_check = DB::select()->from($this->config['table'])->where('id',$id)->execute()->count();
		if($_check){
			DB::update($this->config['table'])->values([
				'data' => serialize($data),
				'updated_at' => 'now()',
			])->where('id',$id)->execute();
		}
		else{
			DB::insert($this->config['table'])->values([
				'id' => $id,
				'data' => serialize($data),
				'updated_at' => 'now()',
			])->execute();
		}
		//Log::coredebug("session wrote");
		return true;
	}
	function read($id)
	{
		$data = NULL;
		$r = DB::select()->from($this->config['table'])->where('id',$id)->execute();
		if( $r->count() ){
			$data = unserialize($r->get('data'));
		}
		//Log::coredebug("session read",$id,$data);
		return $data;
	}
	function close() {}
	function open($savePath, $sessionName) {}
}
