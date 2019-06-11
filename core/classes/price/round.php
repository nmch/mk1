<?php

/**
 * 端数処理
 *
 * @package    App
 * @subpackage Model
 * @author     Hakonet Inc
 */
class Price_Round
{
	protected $rounding_type;
	protected $scale;
	
	/**
	 * Price_Round constructor.
	 *
	 * @param int $type 端数処理区分(0:無効 1:切捨て 2:四捨五入 3:切上げ)
	 */
	function __construct($type, $scale = 0)
	{
		$this->rounding_type = $type;
		$this->scale         = $scale;
	}
	
	/**
	 * @param string $original_value
	 *
	 * @return string
	 */
	function round(string $original_value): string
	{
		bcscale(0);
		
		// $rounding_type 0:無効 1:切捨て 2:四捨五入 3:切上げ
		$value_is_negative = (bccomp($original_value, 0) === -1);
		$positive_value    = $value_is_negative ? bcmul($original_value, '-1', 1) : $original_value;
		/** @var string $value 小数点以下が切り捨てられた絶対値 */
		$integer_positive_value = bcadd($positive_value, '0', 0);
		/** @var string $mod 1未満の数 */
		$mod = bcmod($positive_value, 1, 1);
		switch($this->rounding_type){
			case 0:
			case 1:
				break;
			case 2:
				if( bccomp($mod, '0.5', $this->scale) !== -1 ){
					$integer_positive_value = bcadd($integer_positive_value, 1, 0);
				}
				break;
			case 3:
				if( bccomp($mod, '0', $this->scale) !== 0 ){
					$integer_positive_value = bcadd($integer_positive_value, 1, 0);
				}
				break;
			default:
				throw new UnexpectedValueException();
		}
		
		$value = $value_is_negative ? bcmul($integer_positive_value, '-1', 0) : $integer_positive_value;
		
		return $value;
	}
}
