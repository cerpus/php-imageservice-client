<?php

namespace Cerpus\ImageServiceClient\Clients;


use Cerpus\ImageServiceClient\Contracts\ImageServiceClientContract;
use Cerpus\ImageServiceClient\DataObjects\OauthSetup;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\OAuth2Middleware;

/**
 * Class Oauth2Client
 * @package Cerpus\ImageServiceClient\Clients
 */
class Oauth2Client implements ImageServiceClientContract
{
    /**
     * @param OauthSetup $config
     * @return ClientInterface
     */
    public static function getClient(OauthSetup $config): ClientInterface
    {
        $reauth_client = new Client([
            'base_uri' => $config->authUrl . "/oauth/token",
        ]);
        $reauth_config = [
            "client_id" => $config->authKey,
            "client_secret" => $config->authSecret,
        ];
        $grant_type = new ClientCredentials($reauth_client, $reauth_config);
        $oauth = new OAuth2Middleware($grant_type);

        $stack = HandlerStack::create();
        $stack->push($oauth);

        $client = new Client([
            'base_uri' => $config->baseUrl,
            'handler' => $stack,
            RequestOptions::AUTH => 'oauth',
        ]);
        return $client;
    }
}