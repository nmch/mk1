<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Http_Accept
{
	protected string $requested_accept;
	protected array  $http_accepts = [];
	
	function __construct(string $requested_accept = null)
	{
		$this->requested_accept = $requested_accept ?? \Arr::get($_SERVER, 'HTTP_ACCEPT');
		
		$this->parse_http_accept();
	}
	
	protected function parse_http_accept()
	{
		foreach(explode(',', $this->requested_accept) as $http_accept_item){
			// まず";"をセパレータとしてmedia-rangeとaccept-paramsに分割する
			$exploded_item = explode(';', $http_accept_item);
			
			$media_range   = trim(array_shift($exploded_item));
			$accept_params = $exploded_item;
			
			if( preg_match('#^([^/])+/([^/])+$#', $media_range, $match) ){
				$main_type = $match[0];
				$sub_type  = $match[1];
			}
			else{
				throw new UnexpectedValueException("Invalid mime type ({$media_range})");
			}
			
			$item                 = [
				'main_type'     => $main_type,
				'sub_type'      => $sub_type,
				'accept_params' => $accept_params,
			];
			$this->http_accepts[] = $item;
		}
	}
	
	public function is_acceptable(string $mime_type)
	{
		foreach($this->http_accepts as $http_accept){
		
		}
	}
}
