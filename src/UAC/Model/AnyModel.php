<?php

namespace Codewiser\UAC\Model;

use Carbon\Carbon;
use Codewiser\UAC\Model\Rules\HasRules;
use Codewiser\UAC\Model\Rules\Rules;

abstract class AnyModel
{
    use HasRules;

    protected $data = [];

    /**
     * Cast attributes.
     *
     * @var array
     */
    protected $casts = [];

    public function __construct($data, $rules = [])
    {
        $this->data = $data;
        $this->rules = new Rules($rules);
    }

    /**
     * Cast attribute into object.
     *
     * @param string $attribute
     * @param mixed $value
     * @return mixed
     */
    protected function cast($attribute, $value)
    {
        if (isset($this->casts[$attribute])) {
            $properties = $this->casts[$attribute];

            switch (true) {
                case is_array($properties):
                    $class = current($properties);
                    $array = [];
                    foreach ((array)$value as $item) {
                        $array[] = new $class($item);
                    }
                    return $array;

                case strpos($properties, 'datetime') === 0:
                    return Carbon::parse($value);

                default:
                    return new $properties($value);
            }
        }

        return $value;
    }

    public function toArray()
    {
        return $this->data;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __get($name)
    {
        return $this->cast($name, @$this->data[$name]);
    }

}
