<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

abstract class Text_Line
{
	protected \Text_Builder $text_builder;
	
	function __construct(\Text_Builder $builder)
	{
		$this->text_builder = $builder;
	}
	
	abstract function __toString();
}
