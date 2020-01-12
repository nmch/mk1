<?php

class Text_Line_Text extends Text_Line
{
	protected string $text = '';
	
	function set_text(string $text): \Text_Line_Text
	{
		$this->text = strval($text);
		
		return $this;
	}
	
	function __toString()
	{
		return strval($this->text);
	}
}
