<?php

namespace Cerpus\ImageServiceClient\Providers;


use Cerpus\ImageServiceClient\Clients\Client;
use Cerpus\ImageServiceClient\Clients\Oauth1Client;
use Cerpus\ImageServiceClient\Clients\Oauth2Client;
use Cerpus\ImageServiceClient\Contracts\ImageServiceClientContract;
use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\Exceptions\InvalidConfigException;
use Cerpus\ImageServiceClient\ImageServiceClient;
use Cerpus\ImageServiceClient\DataObjects\OauthSetup;
use Illuminate\Support\ServiceProvider;

class ImageServiceClientServiceProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->app->bind(ImageServiceClientContract::class, function ($app) {
            $ImageServiceClientConfig = $app['config']->get(ImageServiceClient::$alias);
            $adapter = $ImageServiceClientConfig['default'];

            $this->checkConfig($ImageServiceClientConfig, $adapter);

            $adapterConfig = array_merge($this->getDefaultClientStructure(), $ImageServiceClientConfig["adapters"][$adapter]);
            $client = strtolower($adapterConfig['auth-client']);
            /** @var ImageServiceClientContract $clientClass */
            switch ($client) {
                case "oauth1":
                    $clientClass = Oauth1Client::class;
                    break;
                case "oauth2":
                    $clientClass = Oauth2Client::class;
                    break;
                default:
                    $clientClass = Client::class;
                    break;
            }

            return $clientClass::getClient(OauthSetup::create([
                'baseUrl' => $adapterConfig['base-url'],
                'authUrl' => $adapterConfig['auth-url'],
                'authUser' => $adapterConfig['auth-user'],
                'authSecret' => $adapterConfig['auth-secret'],
                'authToken' => $adapterConfig['auth-token'],
                'authTokenSecret' => $adapterConfig['auth-token_secret'],
            ]));
        });

        $this->app->bind(ImageServiceContract::class, function ($app) {
            $client = $app->make(ImageServiceClientContract::class);
            $ImageServiceClientConfig = $app['config']->get(ImageServiceClient::$alias);
            $adapter = $ImageServiceClientConfig['default'];

            $this->checkConfig($ImageServiceClientConfig, $adapter);

            $adapterConfig = $ImageServiceClientConfig["adapters"][$adapter];
            return new $adapterConfig['handler']($client, config("app.key"));
        });

        $this->mergeConfigFrom(ImageServiceClient::getConfigPath(), ImageServiceClient::$alias);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            ImageServiceContract::class,
            ImageServiceClientContract::class,
        ];
    }

    private function getDefaultClientStructure()
    {
        return [
            "handler" => null,
            "base-url" => "",
            "auth-client" => "none",
            "auth-url" => "",
            "auth-user" => "",
            "auth-secret" => "",
            "auth-token" => "",
            "auth-token_secret" => "",
        ];
    }

    private function checkConfig($config, $adapter)
    {
        if (!array_key_exists($adapter, $config['adapters']) || !is_array($config['adapters'][$adapter])) {
            throw new InvalidConfigException(sprintf("Could not find the config for the adapter '%s'", $adapter));
        }
    }
}