<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Database_Expression
{
	protected $expr;

	function __construct($expr)
	{
		$this->expr = $expr;
	}

	function __toString()
	{
		return $this->expr;
	}
}
