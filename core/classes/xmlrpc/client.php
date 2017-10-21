<?php

class Xmlrpc_Client
{
	public $curl;
	public $path;
	public $result;
	
	function __construct($path, $host)
	{
		$this->curl = new Curl([
			Curl::OP_RETURN_AS_JSON => false,
			Curl::OP_BASE_URL       => $host,
		]);
		$this->curl->add_request_headers([
			'Content-Type' => 'text/xml',
		]);
		
		$this->path = $path;
	}
	
	function set_credentials($id, $password)
	{
		$this->curl->set_option(Curl::OP_USERPWD, [$id, $password]);
		
		return $this;
	}
	
	function send($method, $parameters)
	{
		$this->result = null;
		$request      = xmlrpc_encode_request($method, $parameters);
		$this->curl->set_request_raw_data($request);
		$xml = $this->curl->post($this->path);
		//$xml       = file_get_contents('ret_xml.txt');
		$simplexml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
		
		if( $simplexml->getName() !== 'methodResponse' ){
			throw new UnexpectedValueException('methodResponse not found');
		}
		if( $simplexml->fault ){
			$this->result = $this->decode_item($simplexml->fault);
			throw new RuntimeException("XMLRPC Failed");
		}
		
		$value = $simplexml->xpath('/methodResponse/params/param/value');
		if( ! is_array($value) || count($value) !== 1 ){
			throw new UnexpectedValueException('empty value');
		}
		
		$this->result = $this->decode_item($value[0]);
		
		return $this->result;
	}
	
	function decode_item(SimpleXMLElement $item)
	{
		$data = null;
		$name = $item->getName();
		switch($name){
			case 'fault':
			case 'value':
				$children = $item->children();
				$data     = $this->decode_item($children[0]);
				break;
			case 'array':
				$data = [];
				/** @var SimpleXMLElement $array_data */
				foreach($item as $array_data){
					if( $array_data->getName() !== 'data' ){
						throw new Exception("invalid array child");
					}
					/** @var SimpleXMLElement $value */
					foreach($array_data as $value){
						if( $value->getName() !== 'value' ){
							throw new Exception("invalid array data child");
						}
						
						$children   = $value->children();
						$value_data = $this->decode_item($children[0]);
						
						$data[] = $value_data;
					}
				}
				break;
			case 'struct':
				$data = [];
				/** @var SimpleXMLElement $member */
				foreach($item as $member){
					$name = strval($member->name);
					/** @var SimpleXMLElement $value */
					$value      = $member->value;
					$children   = $value->children();
					$value_data = $this->decode_item($children[0]);
					
					$data[$name] = $value_data;
				}
				break;
			case 'i4':
			case 'int':
				$data = intval((string)$item);
				break;
			case 'string':
				$data = (string)$item;
				break;
			case 'dateTime.iso8601':
				$data = (string)$item;
				break;
			default:
				throw new Exception("undefined name {$name}");
			//echo "undefined name {$name}\n";
		}
		
		return $data;
	}
}
