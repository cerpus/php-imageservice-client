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

    public function getHostingUrl($imageId);

    public function get($id): ImageDataObject;

    public function delete($id): bool;
}