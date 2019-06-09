<?php

/**
 * 消費税計算区分
 *
 * @package    App
 * @subpackage Model
 * @author     Hakonet Inc
 */
class Price_Tax_Calc_Type
{
	protected $tax_calc_type;
	protected $permitted_tax_calc_types = [0, 1, 2, 3];
	
	/**
	 * @param int $tax_calc_type 0:無効 1:行毎 2:伝票毎 3:締日毎
	 */
	function __construct($tax_calc_type = 1)
	{
		$this->tax_calc_type = intval($tax_calc_type);
		if( ! in_array($this->tax_calc_type, $this->permitted_tax_calc_types) ){
			throw new UnexpectedValueException();
		}
	}
	
	function get_tax_calc_type(): int
	{
		return $this->tax_calc_type;
	}
	
	/**
	 * 消費税計算を伝票毎に行う設定かどうか
	 *
	 * @return bool
	 */
	function is_tax_calc_per_slip()
	{
		return ($this->tax_calc_type === 2 || $this->tax_calc_type === 3);
	}
}
