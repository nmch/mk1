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
		/** @var string $mod 1未満の数 */
		$mod               = bcmod($original_value, 1, $this->scale);
		$value_is_negative = (bccomp($original_value, 0, $this->scale) === -1);
		/** @var string $value 切り捨てられた絶対値 */
		$value = bcsub($original_value, bcmod($original_value, 1, $this->scale), $this->scale);
		if( $value_is_negative ){
			$value = bcmul($value, '-1', $this->scale);
		}
		switch($this->rounding_type){
			case 0:
			case 1:
				break;
			case 2:
				if( bccomp($mod, '0.5', $this->scale) !== -1 ){
					$value = bcadd($value, 1, $this->scale);
				}
				break;
			case 3:
				if( bccomp($mod, '0', $this->scale) !== 0 ){
					$value = bcadd($value, 1, $this->scale);
				}
				break;
			default:
				throw new UnexpectedValueException();
		}
		
		if( $value_is_negative ){
			$value = bcmul($value, '-1', $this->scale);
		}
		
		return strval(intval($value));
	}
}
