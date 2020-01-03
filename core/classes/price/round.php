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
	const TYPE_FLOOR      = 1;
	const TYPE_FLOOR_CODE = 'floor';
	const TYPE_ROUND      = 2;
	const TYPE_ROUND_CODE = 'round';
	const TYPE_CEIL       = 3;
	const TYPE_CEIL_CODE  = 'ceil';
	
	protected $rounding_type;
	protected $permitted_rounding_types = [0, 1, 2, 3];
	protected $scale;
	
	/**
	 * Price_Round constructor.
	 *
	 * @param int $type 端数処理区分(0:無効 1:切捨て 2:四捨五入 3:切上げ)
	 */
	function __construct($type, int $scale = 0)
	{
		if( ! is_numeric($type) ){
			switch($type){
				case Price_Round::TYPE_FLOOR_CODE:
					$type = Price_Round::TYPE_FLOOR;
					break;
				case Price_Round::TYPE_ROUND_CODE:
					$type = Price_Round::TYPE_ROUND;
					break;
				case Price_Round::TYPE_CEIL_CODE:
					$type = Price_Round::TYPE_CEIL;
					break;
			}
		}
		
		$this->rounding_type = $type;
		if( ! in_array($this->rounding_type, $this->permitted_rounding_types) ){
			throw new UnexpectedValueException();
		}
		
		$this->scale = $scale;
	}
	
	/**
	 * @param string $original_value
	 *
	 * @return string
	 */
	function round(string $original_value): string
	{
		// bcmathで計算する際は全て整数で計算する
		bcscale(0);
		
		/** @var bool $value_is_negative $original_valueは負の数 */
		$value_is_negative = (bccomp($original_value, 0) === -1);
		
		// 端数処理を行うため、10 ^ 指定スケール + 1桁を元の数値に掛けて計算する (最後に戻す)
		$power_factor = bcpow(10, $this->scale + 1);
		$value        = bcmul($original_value, $power_factor);
		
		// この時点で $value 1の桁が端数になっている。
		/** @var string $fraction 端数。10未満の整数。 */
		$fraction = bcmod($value, '10');
		/** @var bool $is_carry_up 繰り上がりフラグ */
		$is_carry_up = false;
		switch($this->rounding_type){        // $rounding_type 0:無効 1:切捨て 2:四捨五入 3:切上げ
			case 0:
			case 1:
				break;
			case 2:
				if( $fraction >= 5 ){
					$is_carry_up = true;
				}
				break;
			case 3:
				if( $fraction > 0 ){
					$is_carry_up = true;
				}
				break;
			default:
				throw new UnexpectedValueException();
		}
		if( $is_carry_up ){
			// 繰り上がり。1の桁は端数なので10を足す。
			$value = bcadd($value, '10');
		}
		
		// 元の値が負の数だった場合は符号を反転する
		if( $value_is_negative ){
			$value = bcmul($value, '-1');
		}
		// スケールを戻す
		$value = bcdiv($value, $power_factor, $this->scale);
		
		return $value;
	}
	
	function get_scale()
	{
		return $this->scale;
	}
}
