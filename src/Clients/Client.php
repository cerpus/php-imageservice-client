<?php

namespace Cerpus\ImageServiceClient\Clients;

use Cerpus\ImageServiceClient\DataObjects\OauthSetup;
use Cerpus\ImageServiceClient\Contracts\ImageServiceClientContract;
use GuzzleHttp\ClientInterface;

/**
 * Class Client
 * @package Cerpus\ImageServiceClient\Clients
 */
class Client implements ImageServiceClientContract
{

    /**
     * @param OauthSetup $config
     * @return ClientInterface
     */
    public static function getClient(OauthSetup $config): ClientInterface
    {
        return new \GuzzleHttp\Client([
            'base_uri' => $config->baseUrl,
        ]);
    }
}