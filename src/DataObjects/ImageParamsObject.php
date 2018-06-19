<?php

namespace Cerpus\ImageServiceClient\DataObjects;


use Cerpus\ImageServiceClient\Traits\CreateTrait;

/**
 * Class ImageParamsObject
 * @package Cerpus\ImageServiceClient\DataObjects
 *
 * @method static ImageParamsObject create($attributes = null)
 */
class ImageParamsObject
{
    use CreateTrait;

    public $maxWidth, $maxHeight, $cropX, $cropY, $cropWidth, $cropHeight, $expireMinutes;

}