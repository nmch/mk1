<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Text_Definition_List extends \Text_Line
{
    protected string $separator = ':';
    /** @var Text_Line_Definition[] */
    protected array $lines = [];

    function add_line(\Text_Line $line): \Text_Definition_List
    {
        $this->lines[] = $line;

        return $this;
    }

    function __toString()
    {
        $max_term_length = 0;
        foreach ($this->lines as $line) {
            if ($line instanceof \Text_Line_Definition) {
                $term_length = $line->get_term_length();
                $max_term_length = max($max_term_length, $term_length);
            }
        }

        $lines = [];
        foreach ($this->lines as $line) {
            if ($line instanceof \Text_Line_Definition) {
                $line->set_term_format_length($max_term_length);
            }

            $lines[] = strval($line);
        }

        $body = $this->text_builder->build($lines);

        return $body;
    }
}
