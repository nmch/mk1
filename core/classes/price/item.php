<?php

/**
 * 明細行
 *
 * @package    App
 * @subpackage Model
 * @author     Hakonet Inc
 */
class Price_Item
{
	/** @var Price_Amount */
	public $amount;
	/** @var Price_Amount */
	public $tax_adjust;
	/** @var Price_Tax_Class */
	public $tax_class;
	/** @var Price_Tax_Rate */
	public $tax_rate;
	/** @var Price_Round */
	public $price_round;
	/** @var Price_Round */
	public $tax_round;
	
	public $additional_data;
	
	public $calc_log = [];
	public $calced_price;
	public $calced_tax_adjust;
	public $calced_price_without_tax;
	public $calced_price_with_tax;
	public $calced_raw_tax_amount;
	public $calced_rounded_tax_amount;
	
	function __construct(
		Price_Amount $amount
		, ?Price_Amount $tax_adjust
		, Price_Tax_Class $tax_class
		, Price_Tax_Rate $tax_rate
		, Price_Round $price_round
		, Price_Round $tax_round
		, $additional_data = null
	)
	{
		$this->amount      = $amount;
		$this->tax_adjust  = $tax_adjust;
		$this->tax_class   = $tax_class;
		$this->tax_rate    = $tax_rate;
		$this->price_round = $price_round;
		$this->tax_round   = $tax_round;
		
		$this->additional_data = $additional_data;
	}
	
	function calc(): Price_Item
	{
		bcscale(0);
		
		$this->calced_price = $this->amount->get_rounded_amount();
		$this->calc_log[]   = "明細金額: {$this->calced_price}";
		
		$this->calced_tax_adjust = $this->tax_adjust ? $this->tax_adjust->get_rounded_amount() : 0;
		$this->calc_log[]        = "調整消費税額: {$this->calced_tax_adjust}";
		
		$scale = 3;
		
		switch($this->tax_class->get_tax_class()){
			case 1: // 外税
				$this->calced_price_without_tax = $this->calced_price;
				
				// 消費税額 = 明細金額 x 消費税率 / 100
				$this->calced_raw_tax_amount = bcdiv(bcmul($this->calced_price, $this->tax_rate->get_rate(), $scale), '100', $scale);
				
				$this->calced_rounded_tax_amount = $this->tax_round->round($this->calced_raw_tax_amount);
				$this->calced_rounded_tax_amount = bcadd($this->calced_rounded_tax_amount, $this->calced_tax_adjust);
				$this->calced_price_with_tax     = bcadd($this->calced_price_without_tax, $this->calced_rounded_tax_amount);
				break;
			case 2: // 内税
				$this->calced_price_with_tax = $this->calced_price;
				
				// 消費税額 = 明細金額 x 消費税率 / (100 + 消費税率)
				$this->calced_raw_tax_amount = bcdiv(
					bcmul($this->calced_price, $this->tax_rate->get_rate(), $scale)
					, bcadd('100', $this->tax_rate->get_rate(), $scale)
					, $scale
				);
				
				$this->calced_rounded_tax_amount = $this->tax_round->round($this->calced_raw_tax_amount);
				$this->calced_rounded_tax_amount = bcadd($this->calced_rounded_tax_amount, $this->calced_tax_adjust);
				$this->calced_price_without_tax  = bcsub($this->calced_price_with_tax, $this->calced_rounded_tax_amount);
				break;
			case 3: // 無税
				$this->calced_price_without_tax  = $this->calced_price;
				$this->calced_price_with_tax     = $this->calced_price;
				$this->calced_raw_tax_amount     = '0';
				$this->calced_rounded_tax_amount = '0';
				break;
			default:
				throw new UnexpectedValueException();
		}
		
		return $this;
	}
	
	function __get($name)
	{
		return is_array($this->additional_data) ? ($this->additional_data[$name] ?? null) : null;
	}
}
