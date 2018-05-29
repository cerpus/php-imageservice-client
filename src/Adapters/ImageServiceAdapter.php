<?php

namespace Cerpus\ImageServiceClient\Adapters;

use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\DataObjects\AnswerDataObject;
use Cerpus\ImageServiceClient\DataObjects\MetadataDataObject;
use Cerpus\ImageServiceClient\DataObjects\QuestionDataObject;
use Cerpus\ImageServiceClient\DataObjects\QuestionsetDataObject;
use Cerpus\ImageServiceClient\DataObjects\SearchDataObject;
use Cerpus\ImageServiceClient\Exceptions\InvalidSearchParametersException;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Class ImageServiceAdapter
 * @package Cerpus\ImageServiceClient\Adapters
 */
class ImageServiceAdapter implements ImageServiceContract
{
    /** @var ClientInterface */
    private $client;

    /** @var Cache */
    private $cache;

    /** @var \Exception */
    private $error;

    /**
     * QuestionBankAdapter constructor.
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client, Cache $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

}