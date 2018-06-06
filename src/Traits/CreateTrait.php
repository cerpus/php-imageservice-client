<?php

namespace Cerpus\ImageServiceClient\Traits;


use Illuminate\Support\Collection;

/**
 * Trait CreateTrait
 * @package Cerpus\ImageServiceClient\Traits
 */
trait CreateTrait
{

    public $wasRecentlyCreated = false;

    /**
     * @param mixed $attributes
     * @return CreateTrait
     */
    public static function create($attributes = null)
    {
        $self = new self();
        if (is_null($attributes)) {
            return $self;
        }
        $properties = get_object_vars($self);
        if (!is_array($attributes)) {
            $arguments = array_pad(func_get_args(), count($properties), null);
            $attributes = array_combine(array_keys($properties), $arguments);
        }
        foreach ($attributes as $attribute => $value) {
            if (!$self->isGuarded($attribute) && array_key_exists($attribute, $properties)) {
                $self->$attribute = $value;
            }
        }

        return $self;
    }

    private function isGuarded($attribute)
    {
        if (strtolower($attribute) === 'guarded') {
            return true;
        }
        return !empty($this->guarded) && in_array($attribute, $this->guarded);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $returnArray = [];
        $properties = get_object_vars($this);
        foreach ($properties as $property => $value) {
            if ($this->$property instanceof Collection) {
                $returnArray[$property] = $this->$property->map(function ($element) {
                    if (method_exists($element, "toArray")) {
                        return $element->toArray();
                    } else {
                        return $element;
                    }
                })->toArray();
            } else {
                $returnArray[$property] = $value;
            }
        }
        return $returnArray;
    }


    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this);
    }
}