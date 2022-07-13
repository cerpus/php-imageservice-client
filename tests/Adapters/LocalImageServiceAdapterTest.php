<?php

namespace Cerpus\ImageServiceClientTests\Adapters;

use Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter;
use Cerpus\ImageServiceClient\Adapters\LocalImageServiceAdapter;
use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\Exceptions\FileNotFoundException;
use Cerpus\ImageServiceClient\Exceptions\ImageUrlNotFoundException;
use Cerpus\ImageServiceClient\Providers\ImageServiceClientServiceProvider;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use Ramsey\Uuid\Uuid;

class LocalImageServiceAdapterTest extends TestCase
{
    private $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = realpath(__DIR__."/../Data/efecabde710777a9a361bd064b07a36e.jpg");
    }

    protected function getPackageProviders($app)
    {
        return [
            ImageServiceClientServiceProvider::class,
        ];
    }

    protected function usesLocalAdapter($app)
    {
        $app->config->set("imageservice-client", [
            "default"  => "imageservice",
            "adapters" => [
                "imageservice" => [
                    "handler"     => LocalImageServiceAdapter::class,
                    "disk-name"   => "public",
                    "system-name" => "",
                ],
            ],
        ]);
    }

    protected function usesImageServiceAdapter($app)
    {
        $app->config->set("imageservice-client", [
            "default"  => "imageservice",
            "adapters" => [
                "imageservice" => [
                    "handler"           => ImageServiceAdapter::class,
                    "base-url"          => "",
                    "auth-client"       => "none",
                    "auth-url"          => "",
                    "auth-user"         => "",
                    "auth-secret"       => "",
                    "auth-token"        => "",
                    "auth-token_secret" => "",
                    "system-name"       => "",
                    "disk-name"         => "public",
                ],
            ],
        ]);
    }

    private function getDisk()
    {
        return Storage::disk(config("imageservice-client.adapters.imageservice.disk-name"));
    }


    public function testNothing()
    {
        $this->assertTrue(true);
    }

    public function testStoreImage_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();

        $response = $imageAdapter->store($this->filePath);

        $this->assertInstanceOf(ImageDataObject::class, $response);
        $this->assertTrue($this->getDisk()->exists("image-service/{$response->id}"));
    }

    public function testGet_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();
        $existingImage = $imageAdapter->store($this->filePath);

        $get = $imageAdapter->get($existingImage->id);

        $this->assertInstanceOf(ImageDataObject::class, $get);
        $this->assertEquals($existingImage->id, $get->id);
        $this->assertEquals($existingImage->size, $get->size);
        $this->assertEquals($existingImage->state, $get->state);
    }


    public function testGet_FileDoesNotExist_Fail()
    {
        $this->expectException(FileNotFoundException::class);

        $imageAdapter = new LocalImageServiceAdapter();

        $imageAdapter->get("something-that-does-not-exist");
    }

    public function testDelete_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();
        $existingImage = $imageAdapter->store($this->filePath);

        $this->assertTrue($this->getDisk()->exists("image-service/{$existingImage->id}"));

        $deleteResult = $imageAdapter->delete($existingImage->id);
        $this->assertTrue($deleteResult);

        $this->assertFalse($this->getDisk()->exists("image-service/{$existingImage->id}"));
    }

    public function testDelete_FileNotFound_Fail()
    {
        $this->expectException(FileNotFoundException::class);

        $imageAdapter = new LocalImageServiceAdapter();
        $imageAdapter->delete("something-that-does-not-exist");
    }

    /**
     * @throws FileNotFoundException
     */
    public function testDelete_UnableToDeleteFile_Fail()
    {
        $imageAdapter = new LocalImageServiceAdapter();
        $existingImage = $imageAdapter->store($this->filePath);

        $this->assertTrue($this->getDisk()->exists("image-service/{$existingImage->id}"));
        $this->getDisk()->setVisibility("image-service/{$existingImage->id}", "private");
        $this->assertEquals("private", $this->getDisk()->getVisibility("image-service/{$existingImage->id}"));

        $deleteResult = $imageAdapter->delete($existingImage->id);

        $this->assertFalse($deleteResult);
        $this->assertTrue($this->getDisk()->exists("image-service/{$existingImage->id}"));
    }

    public function testGetHostingUrl_FileExists_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();
        $existingImage = $imageAdapter->store($this->filePath);

        $hostingUrl = $imageAdapter->getHostingUrl($existingImage->id);
        $this->assertStringEndsWith($existingImage->id, $hostingUrl);
    }

    public function testGetHostingUrl_FileDoesNotExists_Failure()
    {
        $this->expectException(ImageUrlNotFoundException::class);
        $imageAdapter = new LocalImageServiceAdapter();

        $imageAdapter->getHostingUrl("something-that-does-not-exist");
    }

    public function testGetHostingUrls_FileExists_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();
        $existingImage1 = $imageAdapter->store($this->filePath);
        $existingImage2 = $imageAdapter->store($this->filePath);

        $hostingUrls = $imageAdapter->getHostingUrls([$existingImage1->id, $existingImage2->id]);
        $this->assertIsArray($hostingUrls);
        $this->assertCount(2, $hostingUrls);
        $this->assertStringEndsWith($existingImage1->id, $hostingUrls[$existingImage1->id]);
        $this->assertStringEndsWith($existingImage2->id, $hostingUrls[$existingImage2->id]);
    }

    public function testGetErrors_IsArray_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();

        $this->assertIsArray($imageAdapter->getErrors());
        $this->assertEmpty($imageAdapter->getErrors());
    }

    public function testLoadRaw_FileExist_Success()
    {
        $imageAdapter = new LocalImageServiceAdapter();
        $existingImage = $imageAdapter->store($this->filePath);

        $origSha = sha1_file($this->filePath);

        $imageAdapter->loadRaw($existingImage->id, "/tmp/raw");

        $this->assertFileExists("/tmp/raw");
        $this->assertEquals($origSha, sha1_file("/tmp/raw"));

        unlink("/tmp/raw");
    }

    public function testLoadRaw_FileDoesNotExist_Failure()
    {
        $this->expectException(FileNotFoundException::class);
        $imageAdapter = new LocalImageServiceAdapter();

        $imageAdapter->loadRaw("something-that-does-not-exist", "/tmp/raw");
    }

    public function testServiceProviderReturnsCorrectAdapter_LocalImageService()
    {
        $this->app->config->set("imageservice-client", [
            "default"  => "imageservice",
            "adapters" => [
                "imageservice" => [
                    "handler"     => LocalImageServiceAdapter::class,
                    "disk-name"   => "public",
                    "system-name" => "",
                ],
            ],
        ]);

        $adapter = app(ImageServiceContract::class);
        $this->assertEquals(get_class($adapter), LocalImageServiceAdapter::class);
    }

    /**
     * @define-env usesImageServiceAdapter
     */
    public function testServiceProviderReturnsCorrectAdapter_ImageService()
    {
        $adapter = app(ImageServiceContract::class);
        $this->assertEquals(get_class($adapter), ImageServiceAdapter::class);
    }
}
