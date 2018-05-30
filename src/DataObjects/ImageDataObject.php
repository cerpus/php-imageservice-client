<?php

namespace Cerpus\ImageServiceClient\DataObjects;


use Cerpus\ImageServiceClient\Traits\CreateTrait;

class ImageDataObject
{
    use CreateTrait;

    public $id, $state, $size;

}