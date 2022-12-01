<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

abstract class Cache_Driver
{
    protected array $config;

    function __construct(array $config)
    {
        $this->config = $config;
    }

    protected function config($key)
    {
        return Arr::get($this->config, $key);
    }

    protected function hash($value)
    {
        return sha1($value);
    }

    abstract public function set(string $key, $value, ?string $group = null, ?int $expire = null);

    abstract public function get(string $key, ?string $group = null);

    abstract public function clear(?string $key = null, ?string $group = null);
}
