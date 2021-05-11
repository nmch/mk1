<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Response_Json extends Response
{
	function __construct($data = [], $status = 200, array $headers = [])
	{
		parent::__construct(null, $status, $headers);
		$this->set_body_as_json($data);
	}
}
