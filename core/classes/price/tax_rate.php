<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * 消費税率
 */
class Price_Tax_Rate
{
    /** @var string */
    protected $tax_rate;

    function __construct($tax_rate = '0')
    {
        $this->tax_rate = strval($tax_rate);
    }

    function get_rate(): string
    {
        return $this->tax_rate;
    }

    function get_rate_for_index(): string
    {
        $rate_for_index = str_replace('.', '-', $this->tax_rate);

        return $rate_for_index;
    }
}
