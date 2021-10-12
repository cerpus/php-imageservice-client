# Cerpus Image Service Client

PHP library to communicate with the Cerpus Image Service


## Installation
Use composer to require the package
```bash
composer require cerpus/imageservice-client
```


### Laravel
When composer has finished, add the service provider to the `providers` array in `config/app.php`

```php
Cerpus\ImageServiceClient\Providers\ImageServiceClientServiceProvider::class,
```

Add the following to the `alias` array in `config/app.php`
```php
'ImageService' => \Cerpus\ImageServiceClient\ImageServiceClient::class,
```

Publish the config file from the package
```bash
php artisan vendor:publish --provider="Cerpus\ImageServiceClient\Providers\ImageServiceClientServiceProvider" --tag=config
```


### Lumen
*Not tested, but should work. You are welcome to update this documentation if this does not work!*

Add service provider in `app/Providers/AppServiceProvider.php`
```php
public function register()
{
    $this->app->register(Cerpus\ImageServiceClient\Providers\ImageServiceClientServiceProvider::class);
}

```

Uncomment the line that loads the service providers in `bootstrap/app.php`
```php
$app->register(App\Providers\AppServiceProvider::class);
```


### Edit the configuration file

Edit `config/imageservice-client.php`
```php
<?php
return [
    "adapters" => [
        "imageservice" => [
            "handler" => \Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter::class,
            "base-url" => '<url to service>',
            "system-name" => '<system name>',
        ],
    ],
];
```

Example for a developer setup:
```php
<?php

return [
    "adapters" => [
        "imageservice" => [
            "handler" => \Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter::class,
	    "base-url" => env('IMAGESERVICE_URL'),
	    "system-name" => env('VERSION_SYSTEM_NAME')
        ],
    ],
];
```

## Usage
Resolve from the Laravel Container
```php
$cerpusImage = app(Cerpus\ImageServiceClient\Contracts\ImageServiceContract::class)
```
or alias
```php
$cerpusImage = ImageService::<Class Method>
```
or directly
```php
$cerpusImage = new Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter(Client $client, $containerName);
```
The last one is _not_ recommended.

## Class methods
Method calls return an object or throws exceptions on failure.

**get($id)** - Returns an ImageDataObject with info on a particular ID

**store($filePath)** - Creates and uploads a new image in one operation.

**delete($id)** - Delete a file from the image service.

**getHostingUrl($id, ImageParamsObject $params)** - Returns an url where the file can be found.

**getHostingUrls(array $ids)** - Returns an array of urls to images

**loadRaw($id, $toFile)** - Downloads the image to the specified location

**getErrors()** - Returns an array of occurred errors

 ## More info
 See the [Confluence Image storage service API documentation](https://confluence.cerpus.com/pages/viewpage.action?pageId=38535277)


## License

This package is released under the GNU General Public License 3.0. See the
`LICENSE` file for more information.
