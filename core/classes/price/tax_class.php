<?php

/**
 * 税込区分
 *
 * @package    App
 * @subpackage Model
 * @author     Hakonet Inc
 */
class Price_Tax_Class
{
	protected $tax_class;
	protected $permitted_tax_classes = [1, 2, 3];
	
	/**
	 * @param int $tax_class 1:外税 2:内税 3:無税
	 */
	function __construct($tax_class = 1)
	{
		$this->tax_class = intval($tax_class);
		if( ! in_array($this->tax_class, $this->permitted_tax_classes) ){
			throw new UnexpectedValueException();
		}
	}
	
	function get_tax_class(): int
	{
		return $this->tax_class;
	}
}
