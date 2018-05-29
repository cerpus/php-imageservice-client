<?php

namespace Cerpus\ImageServiceClient;

use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Illuminate\Support\Facades\Facade;

/**
 * Class ImageServiceClient
 * @package Cerpus\ImageServiceClient
 *
 */
class ImageServiceClient extends Facade
{

    protected $defer = true;

    /**
     * @var string
     */
    static $alias = "imageservice-client";

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ImageServiceContract::class;
    }

    /**
     * @return string
     */
    public static function getBasePath()
    {
        return dirname(__DIR__);
    }

    /**
     * @return string
     */
    public static function getConfigPath()
    {
        return self::getBasePath() . '/src/Config/' . self::$alias . '.php';
    }
}