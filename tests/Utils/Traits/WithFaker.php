<?php

namespace Cerpus\ImageServiceClientTests\Utils\Traits;


use Faker\Factory;
use Faker\Generator;

trait WithFaker
{
    /** @var  Generator */
    protected $faker;

    public function setUpFaker()
    {
        $this->faker = Factory::create();
    }

}