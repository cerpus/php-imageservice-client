<?php

namespace Cerpus\ImageServiceClient\Contracts;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\DataObjects\ImageParamsObject;

/**
 * Interface ImageServiceContract
 * @package Cerpus\ImageServiceClient\Contracts
 */
interface ImageServiceContract
{
    public function store($imageFilePath): ImageDataObject;

    public function getHostingUrl($imageId, ImageParamsObject $imageParams = null);

    public function get($id): ImageDataObject;

    public function delete($id): bool;

    public function getHostingUrls(array $images);

    public function getErrors();

    public function loadRaw($id, $toFile);
}