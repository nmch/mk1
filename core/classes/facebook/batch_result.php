<?php

class Facebook_BatchResult implements Iterator, Countable, ArrayAccess
{
	protected $results  = [];
	private   $position = 0;

	function add($results)
	{
		if( $results instanceof Facebook_Result ){
			$results = $results->get();
		}

		if( ! is_array($results) ){
			throw new FacebookException('invalid results');
		}
		foreach($results as $key => $result){
			if( ! is_numeric($key) ){
				throw new FacebookException('invalid key type');
			}
			if( empty($result['code']) || empty($result['headers']) || empty($result['body']) ){
				throw new FacebookException('invalid result');
			}
			$result['body'] = json_decode($result['body'], true);
			/*
			foreach($result_block as $result_key => $result_item){
				$result_block[$result_key]['body'] = $result_item["body"] ? json_decode($result_item["body"], true) : '';
				
				if($result_item['code'] == '200'){
				}
				else if($result_item['code'] == '302'){
					//リダイレクト時
					foreach($result_item["headers"] as $header){
						if($header['name'] == 'Location'){
							$result_block[$result_key]['body'] = $header['value'];
							break;
						}
					}
				}
				else{
					//エラー
					$result_block[$result_key]['error_flag'] = true;
				}
			}
			 * 
			 */
			$this->results[] = $result;
			//Log::coredebug("[fb batch result] add result",$result);
		}

		return $this;
	}

	function rewind()
	{
		$this->position = 0;
	}

	function current()
	{
		return $this->fetch();
	}

	function fetch($forward = false, $position = null)    //forwardのデフォルトがfalseなのはforeachかにcurret()のあとにnext()が呼ばれるから。
	{
		if( ! $this->valid() ){
			return null;
		}
		if( $position === null ){
			$position = $this->position;
		}
		$data   =& $this->results[$position];
		$result = new Facebook_Result($data['body'], null, $data['code'], $data['headers']);
		if( $forward ){
			$this->next();
		}

		//Log::coredebug("fetched pos:{$this->position}");
		return $result;
	}

	function valid()
	{
		return ($this->position < $this->count());
	}

	function count()
	{
		return count($this->results);
	}

	function next()
	{
		if( $this->valid() ){
			$this->position++;
		}
	}

	function key()
	{
		return $this->position;
	}

	public function offsetSet($offset, $value)
	{
		if( is_null($offset) ){
			$this->results[] = $value;
		}
		else{
			$this->results[$offset] = $value;
		}
	}

	public function offsetExists($offset)
	{
		return isset($this->results[$offset]);
	}

	public function offsetUnset($offset)
	{
		unset($this->results[$offset]);
	}

	public function offsetGet($offset)
	{
		return $this->fetch(false, $offset);
	}
}
