<?php

class Session_Driver_Mongodb implements SessionHandlerInterface
{
	private $config;
	private $data;
	
	function __construct($config = [])
	{
		$this->config = $config;
	}
	
	function gc($maxlifetime)
	{
		//if( $maxlifetime > 0 ){
		//	$maxlifetime = intval($maxlifetime);
		//	DB::delete()->from($this->config['table'])->where('age(updated_at)', '>', "{$maxlifetime} sec")->execute();
		//}
		
		return true;
	}
	
	function destroy($id)
	{
		$collection = static::get_collection();
		$collection->deleteOne(['id' => $id]);
		
		return true;
	}
	
	private function get_collection_name(): string
	{
		$collection_name = $this->config['collection'] ?? 'sessions';
		
		return $collection_name;
	}
	
	private function get_collection(): \MongoDB\Collection
	{
		$conn            = (\Mongodb_Connection::instance())->get_connection();
		$collection_name = $this->get_collection_name();
		$collection      = $conn->selectCollection($collection_name);
		
		return $collection;
	}
	
	function write($id, $data)
	{
		$encoded_data = base64_encode($data);
		try {
			$decoded_data = unserialize($data);
		} catch(Exception $e){
			$decoded_data = null;
		}
		
		$collection = static::get_collection();
		try {
			$collection->updateOne(['id' => $id], [
				'$set'         => [
					'id'       => $id,
					'php_data' => $encoded_data,
					'data'     => $decoded_data,
				],
				'$currentDate' => [
					'updated_at' => true,
				],
			], [
				'upsert' => true,
			]);
		} catch(Exception $e){
			Log::error("ログデータ保存時にエラーが発生しました", $e);
		}
		
		return true;
	}
	
	function read($id)
	{
		$collection = static::get_collection();
		$r          = $collection->findOne(['id' => $id]);
		
		$data = $r ? base64_decode($r->php_data ?? null) : null;
		
		//Log::coredebug("session read", $id, $data);
		
		return strval($data);
	}
	
	function close()
	{
		return true;
	}
	
	function open($savePath, $sessionName)
	{
		$collection = static::get_collection();
		$r          = $collection->listIndexes();
		
		$index_name = "id_unique";
		
		$index_not_found = true;
		foreach($r as $item){
			if( ($item['name'] ?? null) === $index_name ){
				$index_not_found = false;
				break;
			}
		}
		if( $index_not_found ){
			$collection->createIndex(['id' => 1], [
				'name'   => $index_name,
				'unique' => true,
			]);
		}
		
		return true;
	}
}