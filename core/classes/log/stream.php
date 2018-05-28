<?php

/**
 * ログドライバ : Stream
 */
class Log_Stream implements Logic_Interface_Log_Driver
{
	private $config = [];
	private $fp;
	
	function __construct($config)
	{
		$this->config = $config;
	}
	
	function write($data)
	{
		if( ! $this->fp ){
			$stream   = Arr::get($this->config, 'stream', 'php://stdout');
			$this->fp = fopen($stream, 'w');
			if( $this->fp === false ){
				throw new MkException('cannot open log file');
			}
		}
		
		$format = Arr::get($this->config, 'format', 'plain');
		if( $format === 'json' ){
			$str = json_encode($data);
		}
		elseif( $format === 'json-es' ){
			// for ElasticSearch
			$es_json = [
				'json' => $data,
			];;
			if( $timestamp_unixtime = Arr::get($data, "timestamp_unixtime") ){
				$es_json['@timestamp'] = date(DATE_ATOM, $timestamp_unixtime);
			}
			$es_json['message'] = Arr::get($es_json, "json.message");
			$es_json['uniqid']  = Arr::get($es_json, "json.config.uniqid");
			unset($es_json['json']['config']);
			$str = json_encode($es_json);
		}
		else{
			$str = empty($data['str']) ? '' : $data['str'];
		}
		
		fwrite($this->fp, rtrim($str) . "\n");
	}
}
