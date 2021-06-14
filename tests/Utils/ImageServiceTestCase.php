<?php

namespace Cerpus\ImageServiceClientTests\Utils;

use Cerpus\ImageServiceClientTests\Utils\Traits\WithFaker;
use PHPUnit\Framework\TestCase;

class ImageServiceTestCase extends TestCase
{
    public $testDirectory;

    protected function setUp(): void
    {
        $this->testDirectory = dirname(__FILE__, 2);

        parent::setUp();
        $this->setUpTraits();
    }

    public function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[WithFaker::class])) {
            $this->setUpFaker();
        }
    }
}
