<?php

namespace Cerpus\ImageServiceClient\Contracts;

use Cerpus\ImageServiceClient\DataObjects\OauthSetup;
use GuzzleHttp\ClientInterface;

/**
 * Interface ImageServiceClientContract
 * @package Cerpus\CoreClient\Contracts
 */
interface ImageServiceClientContract
{
    /**
     * @param OauthSetup $config
     * @return ClientInterface
     */
    public static function getClient(OauthSetup $config): ClientInterface;
}