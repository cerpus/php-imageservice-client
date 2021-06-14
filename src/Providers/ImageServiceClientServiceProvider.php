<?php

namespace Cerpus\ImageServiceClient\Providers;


use Cerpus\Helper\Clients\Client;
use Cerpus\Helper\Clients\Oauth1Client;
use Cerpus\Helper\Clients\Oauth2Client;
use Cerpus\Helper\DataObjects\OauthSetup;
use Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter;
use Cerpus\ImageServiceClient\Adapters\LocalImageServiceAdapter;
use Cerpus\ImageServiceClient\Contracts\ImageServiceClientContract;
use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\Exceptions\InvalidConfigException;
use Cerpus\ImageServiceClient\ImageServiceClient;
use Illuminate\Support\ServiceProvider;

class ImageServiceClientServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function boot()
    {
        $this->publishConfig();
    }

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
                'coreUrl' => $adapterConfig['base-url'],
                'authUrl' => $adapterConfig['auth-url'],
                'key' => $adapterConfig['auth-user'],
                'secret' => $adapterConfig['auth-secret'],
                'token' => $adapterConfig['auth-token'],
                'tokenSecret' => $adapterConfig['auth-token_secret'],
            ]));
        });

        $this->app->bind(ImageServiceContract::class, function ($app) {
            $ImageServiceClientConfig = $app['config']->get(ImageServiceClient::$alias);
            $adapter = $ImageServiceClientConfig['default'];
            $this->checkConfig($ImageServiceClientConfig, $adapter);
            $adapterConfig = $ImageServiceClientConfig["adapters"][$adapter];

            $theAdapter = null;
            switch($adapterConfig['handler']){
                case ImageServiceAdapter::class:
                    $client = $app->make(ImageServiceClientContract::class);
                    $theAdapter =  new $adapterConfig['handler']($client, $adapterConfig['system-name']);
                    break;

                case LocalImageServiceAdapter::class:
                    $theAdapter = new $adapterConfig['handler']($adapterConfig['disk-name']);
                    break;
            }

            return $theAdapter;
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
            "disk-name" => "public",
        ];
    }

    private function checkConfig($config, $adapter)
    {
        if (!array_key_exists($adapter, $config['adapters']) || !is_array($config['adapters'][$adapter])) {
            throw new InvalidConfigException(sprintf("Could not find the config for the adapter '%s'", $adapter));
        }
    }

    private function publishConfig()
    {
        $path = ImageServiceClient::getConfigPath();
        $this->publishes([$path => config_path(ImageServiceClient::$alias . ".php")], 'config');
    }
}
