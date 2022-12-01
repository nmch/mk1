<?php

abstract class Factory
{
    protected \Faker\Generator $faker;
    protected string $model;

    function __construct()
    {
        $this->faker = \Faker\Factory::create('ja_JP');
    }

    static function new(): static
    {
        return new static();
    }

    abstract public function definition();

    function create(int $number = 1, array $attributes = []): Model|array
    {
        $created_objects = [];

        for ($c = 0; $c < $number; $c++) {
            $created_objects[] = $this->create_object($attributes);
        }

        return ($number === 1) ? $created_objects[0] : $created_objects;
    }

    function create_object(array $attributes = []): Model
    {
        /** @var Model $obj */
        $obj = new $this->model;


        $model_attributes = $this->definition();
        foreach ($this->state_functions as $state_function) {
            $model_attributes = array_merge($model_attributes, $state_function($obj));
        }
        $model_attributes = array_merge($model_attributes, $attributes);

        foreach ($model_attributes as $index => $attribute) {
            $model_attributes[$index] = is_callable($attribute) ? $attribute($model_attributes) : $attribute;
        }

        $obj->set_array($model_attributes);
        $obj->save();

        foreach ($this->after_creating_functions as $after_creating_function) {
            $after_creating_function($obj);
        }

        return $obj;
    }

    protected array $after_creating_functions = [];
    protected array $state_functions = [];

    function after_creating(callable $callback): static
    {
        $this->after_creating_functions[] = $callback;
        return $this;
    }

    function state(callable $callback): static
    {
        $this->state_functions[] = $callback;
        return $this;
    }
}
