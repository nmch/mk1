<?php

class Facebook_BatchRequest
{
	protected $queries;
	protected $params = [];

	function __construct(array $queries = [], $params = [])
	{
		if( $queries && is_array($queries) ){
			$this->queries = $queries;
		}
		if( $params && is_array($params) ){
			$this->params = array_merge($this->params, $params);
		}
	}

	public static function queries(array $queries = [], $params = [])
	{
		return new static($queries, $params);
	}

	function add($query)
	{
		$this->queries[] = $query;

		return $this;
	}

	function param($name, $value = null)
	{
		if( is_array($name) ){
			foreach($name as $key => $value){
				$this->param($key, $value);
			}
		}
		else{
			if( is_array($value) ){
				$value = implode(',', $value);
			}
			$this->params[$name] = $value;
		}

		return $this;
	}

	function execute()
	{
		$result = new Facebook_BatchResult;
		foreach(array_chunk($this->queries, Config::get('facebook.batch_chunk_size') ?: 50) as $query_block){
			foreach($query_block as $key => $item){
				if( $item instanceof Facebook_Graphapi ){
					$query_block[$key] = [
						'method'       => $item->get_method(),
						'relative_url' => $item->get_api_string(),
					];
				}
				else{
					if( is_string($item) ){
						$query_block[$key] = [
							'method'       => 'GET',
							'relative_url' => $item,
						];
					}
				}
			}
			$query = array_merge($this->params, ['batch' => json_encode($query_block)]);
			//Log::coredebug("query block",$query);
			$result_block = Facebook_Graphapi::query('/', 'POST', $query)->execute();
			$result->add($result_block);
			//Log::coredebug("result block",$result_block);

			unset($result_block);
		}

		return $result;
	}
}