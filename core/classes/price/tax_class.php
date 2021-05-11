<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * 税込区分
 */
class Price_Tax_Class
{
	const TAX_EXCLUDING = 1;
	const TAX_INCLUDING = 2;
	const NOTAX         = 3;
	
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
