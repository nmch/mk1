<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Log_File implements Logic_Interface_Log_Driver
{
	private $config = [];
	private $logfile_dir;
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
		$this->config      = $config;
		$this->logfile_dir = $path;
	}
	
	function __destruct()
	{
		if( $this->fp ){
			fclose($this->fp);
			$this->fp = null;
		}
	}
	
	function write($data)
	{
		if( ! $this->fp ){
			$filename_pattern = Arr::get($this->config, 'filename', 'Ymd');
			$fileext          = Arr::get($this->config, 'fileext', 'log');
			$filename         = date($filename_pattern) . '.' . $fileext;
			$this->fp         = fopen($this->logfile_dir . '/' . $filename, 'at');
			if( $this->fp === false ){
				throw new MkException('cannot open log file');
			}
		}
		
		$format = Arr::get($this->config, 'format', 'plain');
		if( $format === 'json' ){
			if( Arr::get($this->config, 'add_uniqid') ){
				$data['uniqid'] = Arr::get($data, "config.uniqid");
			}
			unset($data['config']);
			$str = json_encode($data, JSON_HEX_TAG
			                          | JSON_HEX_APOS
			                          | JSON_HEX_QUOT
			                          | JSON_HEX_AMP
			                          | JSON_PARTIAL_OUTPUT_ON_ERROR);
		}
		elseif( $format === 'json-es' ){
			// for ElasticSearch
			$es_json = [
				'data' => $data,
			];;
			if( $timestamp_unixtime = Arr::get($data, "timestamp_unixtime") ){
				$es_json['@timestamp'] = date(DATE_ATOM, $timestamp_unixtime);
			}
			$es_json['message'] = Arr::get($es_json, "data.message");
			$es_json['uniqid']  = Arr::get($es_json, "data.config.uniqid");
			unset($es_json['data']['config']);
			$str = json_encode($es_json, JSON_HEX_TAG
			                             | JSON_HEX_APOS
			                             | JSON_HEX_QUOT
			                             | JSON_HEX_AMP
			                             | JSON_PARTIAL_OUTPUT_ON_ERROR);
		}
		else{
			$str = empty($data['str']) ? '' : $data['str'];
		}
		
		fwrite($this->fp, rtrim($str) . "\n");
	}
}
