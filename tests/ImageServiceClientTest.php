<?php

namespace Cerpus\ImageServiceClientTests;


use Cerpus\ImageServiceClient\ImageServiceClient;
use Faker\Provider\Uuid;
use PHPUnit\Framework\TestCase;

class ImageServiceClientTest extends TestCase
{

    /**
     * @test
     */
    public function getBasedir()
    {
        $this->assertEquals(dirname(__DIR__), ImageServiceClient::getBasePath());
    }

    /**
     * @test
     */
    public function getConfigPath()
    {
        $this->assertEquals(dirname(__DIR__) . '/src/Config/imageservice-client.php', ImageServiceClient::getConfigPath());
    }
}