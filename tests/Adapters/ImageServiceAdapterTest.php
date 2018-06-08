<?php

namespace Cerpus\ImageServiceClientTests\Adapters;

use Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\Exceptions\FileNotFoundException;
use Cerpus\ImageServiceClientTests\Utils\ImageServiceTestCase;
use Cerpus\ImageServiceClientTests\Utils\Traits\WithFaker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Teapot\StatusCode;

class ImageServiceAdapterTest extends ImageServiceTestCase
{
    use WithFaker;

    private $containerName = "ImageServiceClientTestContainer";

    /**
     * @test
     */
    public function storeImage_existingFile_thenSuccess()
    {
        $client = $this->createMock(Client::class);

        $imageObjectId = $this->faker->uuid;
        $imagePayload = (object)[
            'id' => $imageObjectId,
            'state' => 'draft',
            'size' => 0,
        ];
        $storedImage = ImageDataObject::create($imageObjectId, 'finished', 1000);

        $responses = [
            new Response(StatusCode::OK, [], json_encode($imagePayload)),
            new Response(StatusCode::OK, [], $storedImage->toJson()),
            new Response(StatusCode::OK, [], json_encode($imagePayload)),
            new Response(StatusCode::OK, [], $storedImage->toJson())
        ];
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $testFile = $this->faker->image('/tmp', 320, 340);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $returnedImage = $adapter->store($testFile);

        $this->assertEquals($storedImage, $returnedImage);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $returnedImage = $adapter->store($this->testDirectory . DIRECTORY_SEPARATOR . "Data" . DIRECTORY_SEPARATOR . "efecabde710777a9a361bd064b07a36e.jpg");

        $this->assertEquals($storedImage, $returnedImage);


    }

    /**
     * @test
     * @expectedException Cerpus\ImageServiceClient\Exceptions\InvalidFileException
     */
    public function storeImage_fileNotInPath_thenFail()
    {
        $client = $this->createMock(Client::class);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->store("/random/directory");
    }

    /**
     * @test
     * @expectedException Cerpus\ImageServiceClient\Exceptions\UploadNotFinishedException
     */
    public function storeImage_fileNoFinished_thenFail()
    {
        $client = $this->createMock(Client::class);

        $imageObjectId = $this->faker->uuid;
        $imagePayload = (object)[
            'id' => $imageObjectId,
            'state' => 'draft',
            'size' => 0,
        ];
        $storedImage = ImageDataObject::create($imageObjectId, 'notfinished', 1000);

        $responses = [
            new Response(StatusCode::OK, [], json_encode($imagePayload)),
            new Response(StatusCode::OK, [], $storedImage->toJson())
        ];
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $testFile = $this->faker->image('/tmp', 320, 340);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $returnedImage = $adapter->store($testFile);

        $this->assertEquals($storedImage, $returnedImage);
    }

    /**
     * @test
     */
    public function getImage_fileFound_thenSuccess()
    {
        $imageId = $this->faker->uuid;
        $imageUrl = $this->faker->imageUrl();

        $mock = new MockHandler([
            new Response(StatusCode::OK, [], json_encode((object)['url' => $imageUrl])),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        Cache::shouldReceive('has')
            ->once()
            ->andReturnFalse();

        Cache::shouldReceive('put')
            ->once()
            ->andReturnNull();

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $this->assertEquals($imageUrl, $adapter->getHostingUrl($imageId));
    }

    /**
     * @test
     */
    public function getImage_fileFoundInCache_thenSuccess()
    {
        $imageUrl = $this->faker->imageUrl();
        $client = $this->createMock(Client::class);
        $client
            ->expects($this->never())
            ->method("request");

        Cache::shouldReceive('has')
            ->once()
            ->andReturnTrue();

        Cache::shouldReceive('get')
            ->once()
            ->andReturn($imageUrl);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $this->assertEquals($imageUrl, $adapter->getHostingUrl($this->faker->uuid));
    }

    /**
     * @test
     */
    public function getImage_emptyImageObjectId_thenSuccess()
    {
        $client = $this->createMock(Client::class);

        Cache::shouldReceive('has')
            ->never();

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $this->assertNull($adapter->getHostingUrl(null));
    }

    /**
     * @test
     */
    public function getImages_allFilesInCache_thenSuccess()
    {
        $images = Collection::times(5, function () {
            return [$this->faker->unique()->uuid => $this->faker->unique()->imageUrl()];
        })->reduce(function ($old, $new) {
            return $old->merge($new);
        }, collect());

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('request');

        Cache::shouldReceive('get')
            ->times(5)
            ->andReturnValues($images->toArray());

        $adapter = new ImageServiceAdapter($client, $this->containerName);

        $this->assertEquals($images->toArray(), $adapter->getHostingUrls($images->keys()->toArray()));
    }

    /**
     * @test
     */
    public function getImages_noFilesInCache_thenSuccess()
    {
        $images = Collection::times(5, function () {
            return [$this->faker->unique()->uuid => $this->faker->unique()->imageUrl()];
        })->reduce(function ($old, $new) {
            return $old->merge($new);
        }, collect());

        $responses = $images
            ->map(function ($url, $id) {
                return new Response(StatusCode::OK, [], json_encode((object)['url' => $url]));
            })
            ->toArray();

        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        Cache::shouldReceive('get')
            ->times(5)
            ->andReturnNull();

        $adapter = new ImageServiceAdapter($client, $this->containerName);

        $this->assertEquals($images->toArray(), $adapter->getHostingUrls($images->keys()->toArray()));
    }

    /**
     * @test
     */
    public function getImages_threeFilesInCache_thenSuccess()
    {
        $images = Collection::times(4, function () {
            return [$this->faker->unique()->uuid => $this->faker->unique()->imageUrl()];
        })->reduce(function ($old, $new) {
            return $old->merge($new);
        }, collect());

        $responses = $images
            ->map(function ($url, $id) {
                return new Response(StatusCode::OK, [], json_encode((object)['url' => $url]));
            })
            ->toArray();
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $cachedId = $this->faker->unique()->uuid;
        $cachedUrl = $this->faker->unique()->imageUrl();

        Cache::shouldReceive('get')
            ->withAnyArgs()
            ->andReturnUsing(function ($key) use ($cachedId, $cachedUrl) {
                if ($key === ImageServiceAdapter::CACHE_KEY . $cachedId) {
                    return $cachedUrl;
                }
                return null;
            });

        $adapter = new ImageServiceAdapter($client, $this->containerName);

        $allImages = $images->merge([$cachedId => $cachedUrl]);
        $this->assertEquals($allImages->toArray(), $adapter->getHostingUrls($allImages->keys()->toArray()));
    }


    /**
     * @test
     */
    public function getImage_validFile_thenSuccess()
    {
        $imageId = $this->faker->uuid;
        $imageObject = ImageDataObject::create($imageId, 'finished', $this->faker->numberBetween(10000, 20000));

        $mock = new MockHandler([
            new Response(StatusCode::OK, [], $imageObject->toJson()),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $imageResponseObject = $adapter->get($imageId);
        $this->assertEquals($imageObject, $imageResponseObject);
        $this->assertAttributeEquals($imageId, 'id', $imageResponseObject);
    }

    /**
     * @test
     * @expectedException Cerpus\ImageServiceClient\Exceptions\FileNotFoundException
     */
    public function getImage_invalidFile_thenFail()
    {
        $imageId = $this->faker->uuid;
        $response = (object)[
            'status' => StatusCode::INTERNAL_SERVER_ERROR,
            'error' => 'Internal Server Error',
            'path' => sprintf(ImageServiceAdapter::GET_IMAGE, $this->containerName, $imageId),
        ];

        $mock = new MockHandler([
            new Response(StatusCode::INTERNAL_SERVER_ERROR, [], json_encode($response)),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->get($imageId);
    }

    /**
     * @test
     */
    public function deleteImage_validImage_thenSuccess()
    {
        $mock = new MockHandler([
            new Response(StatusCode::OK),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $this->assertTrue($adapter->delete($this->faker->uuid));
    }

    /**
     * @test
     * @expectedException Cerpus\ImageServiceClient\Exceptions\FileNotFoundException
     */
    public function deleteImage_invalidImage_thenFail()
    {
        $mock = new MockHandler([
            new Response(StatusCode::INTERNAL_SERVER_ERROR),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->delete($this->faker->uuid);
    }

}
