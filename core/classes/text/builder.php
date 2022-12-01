<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Text_Builder
{
    /** @var \Text_Line[] */
    protected array $lines = [];
    protected string $line_separator = "\n";

    function add_line(\Text_Line $line)
    {
        $this->lines[] = $line;

        return $this;
    }

    function line_separator(string $seprator = null)
    {
        if ($seprator !== null) {
            $this->line_separator = $seprator;
        }

        return $this->line_separator;
    }

    function build(array $data = null): string // Text_Definition_Listがフォーマットを行う場合のためにデータの指定も受け付ける
    {
        $lines = [];

        foreach (($data ?? $this->lines) as $line) {
            $lines[] = strval($line);
        }
        $body = implode($this->line_separator, $lines);
        unset($lines);

        return $body;
    }

    function __toString()
    {
        return $this->build();
    }
}
