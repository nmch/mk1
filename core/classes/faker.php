<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * Faker
 *
 * @mixin \Faker\Generator
 */
class Faker
{
    /** @var \Faker\Generator */
    public $faker;

    function __construct($locale = null)
    {
        $this->faker = static::create($locale);
    }

    static function create($locale = null)
    {
        $faker = Faker\Factory::create($locale ?? Config::get('faker.locale'));
        return $faker;
    }

    function __call(string $name, array $arguments)
    {
        return $this->faker->{$name}(...$arguments);
    }
}
