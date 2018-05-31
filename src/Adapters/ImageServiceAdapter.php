<?php

namespace Cerpus\ImageServiceClient\Adapters;

use Carbon\Carbon;
use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\Exceptions\InvalidFileException;
use Cerpus\ImageServiceClient\Exceptions\UploadNotFinishedException;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Class ImageServiceAdapter
 * @package Cerpus\ImageServiceClient\Adapters
 */
class ImageServiceAdapter implements ImageServiceContract
{
    /** @var ClientInterface */
    private $client;

    private $containerName;

    /** @var \Exception */
    private $error;

    const GET_IMAGE = "/v1/images/%s/%s";
    const CREATE_IMAGE = "/v1/images/%s/create";
    const UPLOAD_IMAGE = "/v1/images/%s/%s/upload";
    const HOSTING_URL = "/v1/images/%s/%s/hosting_url";

    /**
     * QuestionBankAdapter constructor.
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client, $containerName)
    {
        $this->client = $client;
        $this->containerName = $containerName;
    }

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

    private function createImageObject()
    {
        $imageObjectResponse = $this->client->request('POST', sprintf(self::CREATE_IMAGE, $this->containerName), [
            'json' => new \stdClass(),
        ]);
        $responseContent = $imageObjectResponse->getBody()->getContents();
        $imageObject = ImageDataObject::create(\GuzzleHttp\json_decode($responseContent, true));
        return $imageObject;
    }

    private function uploadImage($filePath, $imageObjectId)
    {
        $checksum = sha1_file($filePath);
        $imageUploadResponse = $this->client->request('POST', sprintf(self::UPLOAD_IMAGE . "?checksum=%s", $this->containerName, $imageObjectId, $checksum), [
            'body' => fopen($filePath, 'r')
        ]);
        $imageUploadContent = $imageUploadResponse->getBody()->getContents();
        return ImageDataObject::create(\GuzzleHttp\json_decode($imageUploadContent, true));
    }

    private function isFinished(ImageDataObject $imageDataObject): bool
    {
        return $imageDataObject->state === 'finished';
    }

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

    public function getHostingUrl($imageId)
    {
        $cacheKey = 'ImageServiceObject-' . $imageId;
        if( Cache::has($cacheKey) !== true ){
            $imageResponse = $this->client->request("GET", sprintf(self::HOSTING_URL, $this->containerName, $imageId));
            $imageResponseContent = $imageResponse->getBody()->getContents();
            $responseJson = \GuzzleHttp\json_decode($imageResponseContent);
            $expire = Carbon::now()->addHour();
            Cache::put($responseJson->url, $cacheKey, $expire);
            return $responseJson->url;
        }
        return Cache::get($cacheKey);
    }
}