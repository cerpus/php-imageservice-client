<?php

namespace Cerpus\ImageServiceClientTests\Adapters;

use Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\Exceptions\InvalidFileException;
use Cerpus\ImageServiceClientTests\Utils\ImageServiceTestCase;
use Cerpus\ImageServiceClientTests\Utils\Traits\WithFaker;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Mockery\Mock;
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
        $client = $this->createMock(ClientInterface::class);

        $imageObjectId = $this->faker->uuid;
        $imagePayload = (object)[
            'id' => $imageObjectId,
            'state' => 'draft',
            'size' => 0,
        ];
        $storedImage = ImageDataObject::create($imageObjectId, 'finished', 1000);

        $client
            ->expects($this->exactly(4))
            ->method("request")
            ->willReturnOnConsecutiveCalls(
                new Response(StatusCode::OK, [], json_encode($imagePayload)),
                new Response(StatusCode::OK, [], $storedImage->toJson()),
                new Response(StatusCode::OK, [], json_encode($imagePayload)),
                new Response(StatusCode::OK, [], $storedImage->toJson())
            );

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
        $client = $this->createMock(ClientInterface::class);

        $adapter = new ImageServiceAdapter($client, $this->containerName);
        $adapter->store("/random/directory");
    }

    /**
     * @test
     * @expectedException Cerpus\ImageServiceClient\Exceptions\UploadNotFinishedException
     */
    public function storeImage_fileNoFinished_thenFail()
    {
        $client = $this->createMock(ClientInterface::class);

        $imageObjectId = $this->faker->uuid;
        $imagePayload = (object)[
            'id' => $imageObjectId,
            'state' => 'draft',
            'size' => 0,
        ];
        $storedImage = ImageDataObject::create($imageObjectId, 'notfinished', 1000);

        $client
            ->expects($this->exactly(2))
            ->method("request")
            ->willReturnOnConsecutiveCalls(
                new Response(StatusCode::OK, [], json_encode($imagePayload)),
                new Response(StatusCode::OK, [], $storedImage->toJson())
            );

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
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method("request")
            ->willReturn(new Response(StatusCode::OK, [], json_encode((object)['url' => $imageUrl])));

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
        $client = $this->createMock(ClientInterface::class);
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



}
