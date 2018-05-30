<?php

namespace Cerpus\ImageServiceClient\Contracts;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;

/**
 * Interface ImageServiceContract
 * @package Cerpus\ImageServiceClient\Contracts
 */
interface ImageServiceContract
{
    public function store($imageFilePath): ImageDataObject;

    public function get($imageId): ImageDataObject;
}