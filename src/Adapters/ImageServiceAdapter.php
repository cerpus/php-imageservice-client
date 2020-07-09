<?php

namespace Cerpus\ImageServiceClient\Adapters;

use Carbon\Carbon;
use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\DataObjects\ImageParamsObject;
use Cerpus\ImageServiceClient\Exceptions\FileNotFoundException;
use Cerpus\ImageServiceClient\Exceptions\ImageUrlNotFoundException;
use Cerpus\ImageServiceClient\Exceptions\InvalidFileException;
use Cerpus\ImageServiceClient\Exceptions\UploadNotFinishedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ImageServiceAdapter
 * @package Cerpus\ImageServiceClient\Adapters
 */
class ImageServiceAdapter implements ImageServiceContract
{
    /** @var Client */
    private $client;

    private $containerName;

    const GET_IMAGES = "/v2/containers/%s/images";
    const GET_IMAGE = "/v2/images/%s";
    const CREATE_IMAGE = "/v2/containers/%s/images";
    const UPLOAD_IMAGE = "/v2/images/%s/upload";
    const HOSTING_URL = "/v2/images/%s/hosting_url";

    const CACHE_KEY = 'ImageServiceObject-';

    const CACHE_EXPIRE = 60 * 23;

    private $errors = [];

    /**
     * QuestionBankAdapter constructor.
     * @param Client $client
     * @param string $containerName
     */
    public function __construct(Client $client, $containerName)
    {
        $this->client = $client;
        $this->containerName = $containerName;
    }

    /**
     * @param string $containerName
     */
    public function setContainerName(string $containerName)
    {
        $this->containerName = $containerName;
    }

    /**
     * @param $imageFilePath
     * @return ImageDataObject
     * @throws InvalidFileException
     * @throws UploadNotFinishedException
     */
    public function store($imageFilePath): ImageDataObject
    {
        if ($this->isFileInPath($imageFilePath) !== true) {
            throw new InvalidFileException();
        }
        $imageObject = $this->createImageObject();
        $uploadedImage = $this->uploadImage($imageFilePath, $imageObject->id);

        if ($this->isFinished($uploadedImage) !== true) {
            throw new UploadNotFinishedException("The uploaded file is not finished");
        }
        return $uploadedImage;
    }

    /**
     * @return ImageDataObject
     */
    private function createImageObject()
    {
        $imageObjectResponse = $this->client->post(sprintf(self::CREATE_IMAGE, $this->containerName), [
            'json' => new \stdClass(),
        ]);
        $responseContent = $imageObjectResponse->getBody()->getContents();
        $imageObject = ImageDataObject::create(\GuzzleHttp\json_decode($responseContent, true));
        return $imageObject;
    }

    /**
     * @param $filePath
     * @param $imageObjectId
     * @return ImageDataObject
     */
    private function uploadImage($filePath, $imageObjectId)
    {
        $checksum = sha1_file($filePath);
        $imageUploadResponse = $this->client->post(sprintf(self::UPLOAD_IMAGE, $imageObjectId), [
            'body' => fopen($filePath, 'r'),
            'query' => [
                'checksum' => $checksum
            ]
        ]);
        $imageUploadContent = $imageUploadResponse->getBody()->getContents();
        return ImageDataObject::create(\GuzzleHttp\json_decode($imageUploadContent, true));
    }

    /**
     * @param ImageDataObject $imageDataObject
     * @return bool
     */
    private function isFinished(ImageDataObject $imageDataObject): bool
    {
        return $imageDataObject->state === 'finished';
    }

    /**
     * @param $filePath
     * @return bool
     */
    private function isFileInPath($filePath)
    {
        $realPath = realpath($filePath);
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);

        if (strpos($realPath, sys_get_temp_dir()) === 0 ||
            strpos($realPath, $documentRoot) === 0) {
            return true;
        }
        return false;
    }

    /**
     * @param $imageId
     * @param ImageParamsObject|null $imageParams
     * @return string|null
     * @throws ImageUrlNotFoundException
     */
    public function getHostingUrl($imageId, ImageParamsObject $imageParams = null)
    {
        if (empty($imageId)) {
            return null;
        }

        $cacheKey = $this->getCacheKey($imageId, $imageParams);
        if (Cache::has($cacheKey) !== true) {
            try {
                $imageResponse = $this->client->get(sprintf(self::HOSTING_URL, $imageId), [
                    'query' => is_null($imageParams) ? [] : $imageParams->toArray()
                ]);
                $imageResponseContent = $imageResponse->getBody()->getContents();
                $responseJson = \GuzzleHttp\json_decode($imageResponseContent);
                $this->addToCache($responseJson->url, $cacheKey);
                return $responseJson->url;
            } catch (RequestException $exception) {
                $this->errors[$imageId] = $exception;
                throw new ImageUrlNotFoundException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }
        return Cache::get($cacheKey);
    }

    /**
     * @param $imageId
     * @param ImageParamsObject|null $paramsObject
     * @return string
     */
    private function getCacheKey($imageId, ImageParamsObject $paramsObject = null)
    {
        return is_null($paramsObject) ? self::CACHE_KEY . $imageId : self::CACHE_KEY . $imageId . '|' . implode('|', $paramsObject->toArray());
    }

    /**
     * @param $url
     * @param $cacheKey
     */
    private function addToCache($url, $cacheKey)
    {
        $expire = Carbon::now()->addMinutes(self::CACHE_EXPIRE);
        Cache::put($cacheKey, $url, $expire);
    }


    /**
     * @param array $imageIds
     * @return array
     */
    public function getHostingUrls(array $imageIds)
    {
        $imageObjects = collect($imageIds)
            ->flatMap(function ($value, $index) {
                if (is_array($value)) {
                    return [$index => [
                        'url' => null,
                        'params' => $value['params']
                    ]];
                } else {
                    return [$value => [
                        'url' => null,
                        'params' => null
                    ]];
                }
            });

        $cached = $imageObjects
            ->map(function ($value, $imageId) {
                $cacheKey = $this->getCacheKey($imageId, $value['params']);
                return Cache::get($cacheKey);
            })
            ->filter(function ($imageUrl) {
                return !empty($imageUrl);
            });

        if ($cached->count() === count($imageIds)) {
            return $cached->toArray();
        }

        $notCached = $imageObjects->diffKeys($cached);
        $doRequest = function (&$images) {
            foreach ($images as $imageId => $image) {
                yield function () use ($imageId, &$images) {
                    return $this->client
                        ->getAsync(sprintf(self::HOSTING_URL, $imageId), [
                            'query' => is_null($images[$imageId]['params']) ? [] : $images[$imageId]['params']->toArray()
                        ])
                        ->then(function (ResponseInterface $response) use ($imageId, &$images) {
                            $responseContent = \GuzzleHttp\json_decode($response->getBody()->getContents());
                            $this->addToCache($responseContent->url, $this->getCacheKey($imageId, $images[$imageId]['params']));
                            $images[$imageId] = $responseContent->url;
                        }, function ($exception) use ($imageId, &$images) {
                            $images[$imageId] = null;
                            $this->errors[$imageId] = $exception;
                        });
                };
            }
        };

        $startWork = $notCached->toArray();
        $pool = new Pool($this->client, $doRequest($startWork));
        $promise = $pool->promise();
        $promise->wait();

        $imageObjects = $imageObjects->merge($startWork)->merge($cached);

        return $imageObjects->toArray();
    }

    /**
     * @param $id
     * @return ImageDataObject
     * @throws FileNotFoundException
     */
    public function get($id): ImageDataObject
    {
        try {
            $imageResponse = $this->client->get(sprintf(self::GET_IMAGE, $id));
            $imageResponseContent = $imageResponse->getBody()->getContents();
            return ImageDataObject::create(\GuzzleHttp\json_decode($imageResponseContent, true));
        } catch (RequestException $exception) {
            throw new FileNotFoundException($exception->getMessage());
        }
    }

    /**
     * @param $id
     * @param  string $toFile
     * @throws FileNotFoundException|ImageUrlNotFoundException
     */
    public function loadRaw($id, $toFile)
    {
        try {
            $url = $this->getHostingUrl($id);
            if (!$url) {
                throw new FileNotFoundException();
            }
            $this->client->get($url, ['sink' => $toFile]);
        } catch (RequestException $exception) {
            throw new FileNotFoundException($exception->getMessage());
        }
    }

    /**
     * @param $id
     * @return bool
     * @throws FileNotFoundException
     */
    public function delete($id): bool
    {
        try {
            $imageResponse = $this->client->delete(sprintf(self::GET_IMAGE, $id));
            return $imageResponse->getStatusCode() === 200;
        } catch (RequestException $exception) {
            throw new FileNotFoundException($exception->getMessage());
        }
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}