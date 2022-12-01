<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * 消費税計算区分
 */
class Price_Tax_Calc_Type
{
    const TYPE_NOTAX = 0;
    const TYPE_NOTAX_CODE = 'notax';
    const TYPE_PER_LINE = 1;
    const TYPE_PER_LINE_CODE = 'per-line';
    const TYPE_PER_SLIP = 2;
    const TYPE_PER_SLIP_CODE = 'per-slip';

    protected $tax_calc_type;
    protected $permitted_tax_calc_types = [0, 1, 2, 3];

    /**
     * @param  int  $tax_calc_type  0:無効 1:行毎 2:伝票毎 3:締日毎
     */
    function __construct($tax_calc_type = 1)
    {
        if (!is_numeric($tax_calc_type)) {
            switch ($tax_calc_type) {
                case Price_Tax_Calc_Type::TYPE_NOTAX_CODE:
                    $tax_calc_type = Price_Tax_Calc_Type::TYPE_NOTAX;
                    break;
                case Price_Tax_Calc_Type::TYPE_PER_LINE_CODE:
                    $tax_calc_type = Price_Tax_Calc_Type::TYPE_PER_LINE;
                    break;
                case Price_Tax_Calc_Type::TYPE_PER_SLIP_CODE:
                    $tax_calc_type = Price_Tax_Calc_Type::TYPE_PER_SLIP;
                    break;
            }
        }

        $this->tax_calc_type = intval($tax_calc_type);
        if (!in_array($this->tax_calc_type, $this->permitted_tax_calc_types)) {
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
