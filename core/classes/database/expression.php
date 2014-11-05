<?
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
