<?php


namespace Cerpus\ImageServiceClient\Adapters;


use Cerpus\ImageServiceClient\Contracts\ImageServiceContract;
use Cerpus\ImageServiceClient\DataObjects\ImageDataObject;
use Cerpus\ImageServiceClient\DataObjects\ImageParamsObject;
use Cerpus\ImageServiceClient\Exceptions\FileNotFoundException;
use Cerpus\ImageServiceClient\Exceptions\ImageUrlNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalImageServiceAdapter implements ImageServiceContract
{
    private $errors = [];
    private $disk = null;
    private $diskName = null;

    public function __construct(?string $diskName = null)
    {
        if ($diskName) {
            $this->diskName = $diskName;
        } else {
            $this->diskName = config("imageservice-client.adapters.imageservice.disk-name");
        }
    }

    private function getImageDataObject(): ImageDataObject
    {
        $imageData = app(ImageDataObject::class);
        $imageData->wasRecentlyCreated = true;
        $imageData->setIsDirty(false);
        $imageData->state = "finished";

        return $imageData;
    }

    private function getDisk(): Filesystem
    {
        if (!$this->disk) {
            $this->disk = Storage::disk($this->diskName);
        }

        return $this->disk;
    }

    public function store($imageFilePath): ImageDataObject
    {
        $id = $this->getDisk()->putFileAs('image-service', new File($imageFilePath), Str::uuid()->toString());
        $this->getDisk()->setVisibility($id, "public");

        $publicId = basename($id);

        $imageData = $this->getImageDataObject();
        $imageData->id = $publicId;
        $imageData->size = $this->getDisk()->size($id);

        return $imageData;
    }

    public function getHostingUrl($imageId, ImageParamsObject $imageParams = null)
    {
        if (!$this->getDisk()->exists("image-service/{$imageId}")) {
            throw new ImageUrlNotFoundException;
        }

        $url = $this->getDisk()->url("image-service/{$imageId}");

        return $url;
    }

    public function get($id): ImageDataObject
    {
        if (!$this->getDisk()->exists("image-service/{$id}")) {
            throw new FileNotFoundException();
        }

        $imageData = $this->getImageDataObject();
        $imageData->id = $id;
        $imageData->size = $this->getDisk()->size("image-service/$id");

        return $imageData;
    }

    public function delete($id): bool
    {
        if (!$this->getDisk()->exists("image-service/$id")) {
            throw new FileNotFoundException();
        }

        if ($this->getDisk()->getVisibility("image-service/{$id}") === "private") {
            return false;
        }

        $this->getDisk()->delete("image-service/$id");

        return true;
    }

    public function getHostingUrls(array $images)
    {
        $hostingUrls = [];

        foreach ($images as $imageId) {
            $hostingUrls[$imageId] = $this->getHostingUrl($imageId);
        }

        return $hostingUrls;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function loadRaw($id, $toFile)
    {
        if (!$this->getDisk()->exists("image-service/$id")) {
            throw new FileNotFoundException();
        }

        file_put_contents($toFile, $this->getDisk()->get("image-service/{$id}"));
    }
}
