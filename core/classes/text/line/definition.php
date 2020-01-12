<?php

class Text_Line_Definition extends Text_Line
{
	protected string $separator          = ': ';
	protected string $term               = '';
	protected string $description        = '';
	protected int    $term_format_length = 0;
	
	function set_term(string $text): \Text_Line_Definition
	{
		$this->term = strval($text);
		
		return $this;
	}
	
	function set_desc(...$args): \Text_Line_Definition
	{
		$description = null;
		if( count($args) === 1 ){
			if( is_scalar($args[0]) ){
				$description = $args[0];
			}
			else{
				throw new UnexpectedValueException();
			}
		}
		elseif( count($args) === 2 ){
			if( is_array($args[0]) && is_string($args[1]) ){
				$description = \Arr::get($args[0], $args[1]);
			}
			else{
				throw new UnexpectedValueException();
			}
		}
		else{
			throw new UnexpectedValueException();
		}
		
		$this->description = strval($description);
		
		return $this;
	}
	
	function get_term_length(): int
	{
		return mb_strwidth($this->term);
	}
	
	function set_term_format_length(int $length)
	{
		$this->term_format_length = $length;
		
		return $this;
	}
	
	function __toString()
	{
		$line = '';
		$line .= $this->term;
		if( $this->term_format_length ){
			$padding = ($this->term_format_length - $this->get_term_length());
			$line    .= str_repeat(' ', $padding);
		}
		$line .= $this->separator;
		$line .= $this->description;
		
		return strval($line);
	}
}
