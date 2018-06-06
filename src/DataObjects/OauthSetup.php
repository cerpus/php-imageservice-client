<?php

namespace Cerpus\ImageServiceClient\DataObjects;


use Cerpus\ImageServiceClient\Traits\CreateTrait;

/**
 * Class OauthSetup
 * @package Cerpus\ImageServiceClient\DataObjects
 *
 * @method static OauthSetup create($attributes = null)
 */
class OauthSetup
{
    use CreateTrait;

    /**
     * @var string $key
     * @var string $secret
     * @var string $url
     * @var string $authUrl
     * @var string $tokenSecret
     * @var string $token
     */
    public $baseUrl, $authKey, $authSecret, $authUrl, $authTokenSecret, $authToken;
}