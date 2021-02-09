<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Http_Client extends \GuzzleHttp\Client
{
	function __construct(array $config = [])
	{
		$config += [
			\GuzzleHttp\RequestOptions::TIMEOUT => 5,
			\GuzzleHttp\RequestOptions::PROXY   => getenv('HTTP_PROXY'),
		];
		
		parent::__construct($config);
	}
	
	function request_json(string $method, $uri = '', array $options = [])
	{
		$r = parent::request($method, $uri, $options);
		
		return \GuzzleHttp\Utils::jsonDecode($r->getBody()->getContents(), true);
	}
}
