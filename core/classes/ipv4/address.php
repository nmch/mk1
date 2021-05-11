<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Ipv4_Address
{
	protected $ipv4_address_string;
	protected $ipv4_address_array;
	
	function __construct($ipv4_address)
	{
		if( is_string($ipv4_address) ){
			$exploded_address = explode('.', $ipv4_address);
		}
		elseif( is_array($ipv4_address) ){
			$exploded_address = $ipv4_address;
		}
		else{
			throw new UnexpectedValueException();
		}
		
		if( count($exploded_address) !== 4 ){
			throw new UnexpectedValueException();
		}
		foreach($exploded_address as $segment){
			if( $segment < 0 || 255 < $segment ){
				throw new UnexpectedValueException();
			}
		}
		
		$this->ipv4_address_string = $ipv4_address;
		$this->ipv4_address_array  = $exploded_address;
	}
	
	function mask(array $mask): Ipv4_Address
	{
		return new Ipv4_Address([
			$this->ipv4_address_array[0] & $mask[0],
			$this->ipv4_address_array[1] & $mask[1],
			$this->ipv4_address_array[2] & $mask[2],
			$this->ipv4_address_array[3] & $mask[3],
		]);
	}
	
	function __toString()
	{
		return "{$this->ipv4_address_array[0]}.{$this->ipv4_address_array[1]}.{$this->ipv4_address_array[2]}.{$this->ipv4_address_array[3]}";
	}
	
	function equal($address)
	{
		if( is_string($address) ){
			return (strval($this) === $address);
		}
		else{
			throw new UnexpectedValueException();
		}
	}
	
	function is_private_address()
	{
		return (
			$this->mask([255, 0, 0, 0])->equal('10.0.0.0')
			|| $this->mask([255, 240, 0, 0])->equal('172.16.0.0')
			|| $this->mask([255, 255, 0, 0])->equal('192.168.0.0')
		);
	}
}
