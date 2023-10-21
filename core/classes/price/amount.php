<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * 金額
 */
class Price_Amount implements JsonSerializable
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
        $this->qty = $qty;

        $this->set_rounding_type($round ?? new Price_Round(\Price_Round::TYPE_ROUND));
    }

    function jsonSerialize()
    {
        return [
            'unit_price' => $this->unit_price,
            'qty' => $this->qty,
            'rounding_type' => $this->rounding_type,
            'raw_amount' => $this->raw_amount,
            'rounded_amount' => $this->rounded_amount,
        ];
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
        if (!$this->rounding_type) {
            throw new Exception();
        }
        if ($this->raw_amount === null) {
            $this->raw_amount = bcmul($this->unit_price, $this->qty, $this->rounding_type->get_scale() + 1);
        }

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
