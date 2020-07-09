<?php

namespace Cerpus\ImageServiceClientTests\Adapters;

use Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\DataObjects\ImageParamsObject;
use Cerpus\ImageServiceClient\Exceptions\FileNotFoundException;
use Cerpus\ImageServiceClient\Exceptions\ImageUrlNotFoundException;
use Cerpus\ImageServiceClient\Exceptions\InvalidFileException;
use Cerpus\ImageServiceClient\Exceptions\UploadNotFinishedException;
use Cerpus\ImageServiceClientTests\Utils\ImageServiceTestCase;
use Cerpus\ImageServiceClientTests\Utils\Traits\WithFaker;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Teapot\StatusCode;

class ImageServiceAdapterTest extends ImageServiceTestCase
{
    use WithFaker;

    private $containerName = "ImageServiceClientTestContainer";

    private $testFiles = [];

    protected function setUp()
    {
        parent::setUp();

        $this->testFiles = [];
    }

    protected function tearDown()
    {
        parent::tearDown();

        array_walk($this->testFiles, function ($file) {
            if( file_exists($file)){
                unlink($file);
            }
        });
    }

    /**
     * @test
     */
    public function storeImage_validateClient()
    {
        $imageObjectId = $this->faker->uuid;
        $imagePayload = (object)[
            'id' => $imageObjectId,
            'state' => 'draft',
            'size' => 0,
        ];
        $storedImage = ImageDataObject::create($imageObjectId, 'finished', 1000);

        $testFile = $this->faker->image('/tmp', 320, 340);
        $this->testFiles[] = $testFile;

        $client = $this->getMockBuilder(Client::class)
            ->setMethods(['post'])
            ->getMock();

        $client
            ->expects(self::at(0))
            ->method('post')
            ->with(sprintf(ImageServiceAdapter::CREATE_IMAGE, $this->containerName), $this->anything())
            ->willReturn(
                new Response(StatusCode::OK, [], json_encode($imagePayload))
            );

        $client
            ->expects(self::at(1))
            ->method('post')
            ->with(sprintf(ImageServiceAdapter::UPLOAD_IMAGE, $imageObjectId), $this->anything())
            ->willReturn(
                new Response(StatusCode::OK, [], $storedImage->toJson())
            );

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $returnedImage = $adapter->store($testFile);
        $this->assertEquals($storedImage, $returnedImage);
    }

    /**
     * @test
     */
    public function storeImage_existingFile_thenSuccess()
    {
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
        $this->testFiles[] = $testFile;

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $returnedImage = $adapter->store($testFile);
        $this->assertEquals($storedImage, $returnedImage);

        $returnedImage = $adapter->store($this->testDirectory . DIRECTORY_SEPARATOR . "Data" . DIRECTORY_SEPARATOR . "efecabde710777a9a361bd064b07a36e.jpg");
        $this->assertEquals($storedImage, $returnedImage);
    }

    /**
     * @test
     *
     */
    public function storeImage_fileNotInPath_thenFail()
    {
        $this->expectException(InvalidFileException::class);
        $client = $this->createMock(Client::class);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->store("/random/directory/" . $this->faker->uuid);
    }

    /**
     * @test
     *
     */
    public function storeImage_fileNoFinished_thenFail()
    {
        $this->expectException(UploadNotFinishedException::class);
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
        $this->testFiles[] = $testFile;

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $returnedImage = $adapter->store($testFile);
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
    public function checkClientParameters_fileFound_thenSuccess()
    {
        $imageId = $this->faker->uuid;
        $imageUrl = $this->faker->imageUrl();

        $client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $client
            ->expects(self::once())
            ->method('get')
            ->with(sprintf(ImageServiceAdapter::HOSTING_URL, $imageId), $this->anything())
            ->willReturn(
                new Response(StatusCode::OK, [], json_encode((object)['url' => $imageUrl]))
            );

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

        /** @var Client $client */
        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $this->assertEquals($imageUrl, $adapter->getHostingUrl($this->faker->uuid));
    }

    /**
     * @test
     *
     */
    public function getImage_errorOnServer_thenFail()
    {
        $this->expectException(ImageUrlNotFoundException::class);
        $mock = new MockHandler([
            new RequestException("Could not find url", new Request("GET", 'test')),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        Cache::shouldReceive('has')
            ->once()
            ->andReturnFalse();

        /** @var Client $client */
        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->getHostingUrl($this->faker->uuid);
    }

    /**
     * @test
     */
    public function getImage_emptyImageObjectId_thenSuccess()
    {
        /** @var Client $client */
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
        $images = collect([
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
        ]);

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('request');

        Cache::shouldReceive('get')
            ->times(5)
            ->andReturnValues($images->toArray());

        /** @var Client $client */
        $adapter = new ImageServiceAdapter($client, $this->containerName);

        $this->assertEquals($images->toArray(), $adapter->getHostingUrls($images->keys()->toArray()));
    }

    /**
     * @test
     */
    public function checkClientParameters_noFilesInCache_thenSuccess()
    {
        $imageId = $this->faker->uuid;
        $imageUrl = $this->faker->imageUrl();

        $images = collect([
            $imageId => $imageUrl,
        ]);

        $client = $this->getMockBuilder(Client::class)
            ->setMethods(['getAsync'])
            ->getMock();

        $client
            ->expects(self::once())
            ->method('getAsync')
            ->with(sprintf(ImageServiceAdapter::HOSTING_URL, $imageId), $this->anything())
            ->willReturn(
                new FulfilledPromise(new Response(StatusCode::OK, [], json_encode((object)['url' => $imageUrl])))
            );

        Cache::shouldReceive('get')
            ->once()
            ->andReturnNull();

        /** @var Client $client */
        $adapter = new ImageServiceAdapter($client, $this->containerName);

        $t = $adapter->getHostingUrls($images->keys()->toArray());
        $this->assertEquals($images->toArray(), $t);
    }

    /**
     * @test
     */
    public function getImages_noFilesInCache_thenSuccess()
    {
        $images = collect([
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
        ]);

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
    public function getImagesWithParams_noFilesInCache_thenSuccess()
    {
        $images = collect([
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
        ]);

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

        $imageArray = $images
            ->map(function () {
                return [
                    'params' => ImageParamsObject::create(['maxWidth' => 200])
                ];
            })
            ->toArray();

        $this->assertEquals($images->toArray(), $adapter->getHostingUrls($imageArray));
    }

    /**
     * @test
     */
    public function getImages_threeFilesInCache_thenSuccess()
    {
        $images = collect([
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
        ]);


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
    public function getImages_noFilesInCacheOneFailure_thenSuccess()
    {
        $images = collect([
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
            $this->faker->unique()->uuid => null,
            $this->faker->unique()->uuid => $this->faker->unique()->imageUrl(),
        ]);

        $responses = $images
            ->map(function ($url, $id) {
                if (!is_null($url)) {
                    return new Response(StatusCode::OK, [], json_encode((object)['url' => $url]));
                } else {
                    return new Response(StatusCode::INTERNAL_SERVER_ERROR, [], json_encode((object)['url' => $url]));
                }
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
        $this->assertNotEmpty($adapter->getErrors());
        $this->assertCount(1, $adapter->getErrors());
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
     *
     */
    public function getImage_invalidFile_thenFail()
    {
        $this->expectException(FileNotFoundException::class);
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
     *
     */
    public function deleteImage_invalidImage_thenFail()
    {
        $this->expectException(FileNotFoundException::class);
        $mock = new MockHandler([
            new Response(StatusCode::INTERNAL_SERVER_ERROR),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->delete($this->faker->uuid);
    }

    /**
     * @test
     *
     */
    public function loadRawImage_validPath_thenSuccess()
    {
        $destinationFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "imageTest_" . $this->faker->uuid;
        $this->testFiles[] = $destinationFile;
        $imageUrl = $this->faker->imageUrl();

        $mock = new MockHandler([
            new Response(StatusCode::OK, [], json_encode((object)['url' => $imageUrl])),
            new Response(StatusCode::OK, [], file_get_contents($this->testDirectory . DIRECTORY_SEPARATOR . "Data" . DIRECTORY_SEPARATOR . "efecabde710777a9a361bd064b07a36e.jpg")),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->assertFileNotExists($destinationFile);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->loadRaw($this->faker->randomNumber(4), $destinationFile);

        $this->assertFileExists($destinationFile);
    }

    /**
     * @test
     *
     */
    public function loadRawImage_imageNotFound_thenFail()
    {
        $this->expectException(ImageUrlNotFoundException::class);

        $destinationFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "imageTest_" . $this->faker->uuid;
        $this->testFiles[] = $destinationFile;
        $imageUrl = $this->faker->imageUrl();

        $mock = new MockHandler([
            new Response(StatusCode::NOT_FOUND),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->assertFileNotExists($destinationFile);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->loadRaw($this->faker->randomNumber(4), $destinationFile);
    }
}
