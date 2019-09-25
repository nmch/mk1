<?php

/**
 * 金額
 *
 * @package    App
 * @subpackage Model
 * @author     Hakonet Inc
 */
class Price_Amount
{
	protected $unit_price;
	protected $qty;
	protected $raw_amount;
	protected $rounded_amount;
	
	/** @var Price_Round */
	private $rounding_type;
	
	function __construct($unit_price = '0', $qty = '0', ?Price_Round $round = null)
	{
		$this->unit_price = $unit_price;
		$this->qty        = $qty;
		$this->raw_amount = bcmul($unit_price, $qty, 0);
		
		$this->set_rounding_type($round);
	}
	
	function set_raw_amount($value)
	{
		$this->raw_amount = strval($value);
		
		return $this;
	}
	
	function set_rounding_type(Price_Round $round)
	{
		$this->rounding_type = $round;
		
		return $this;
	}
	
	function get_rounded_amount(): string
	{
		$this->rounded_amount = $this->rounding_type ? $this->rounding_type->round($this->raw_amount) : $this->raw_amount;
		
		return $this->rounded_amount;
	}
	
	function get_qty()
	{
		return $this->qty;
	}
	
	function get_unit_price()
	{
		return $this->unit_price;
	}
}
