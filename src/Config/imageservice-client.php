<?php

return [
    //default adapter for questionsets
        "default" => "imageservice",

    "adapters" => [

        "imageservice" => [
            "handler" => env("IMAGE_SERVICE_HANDLER", \Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter::class),
            "base-url" => "",
            "auth-client" => "none",
            "auth-url" => "",
            "auth-user" => "",
            "auth-secret" => "",
            "auth-token" => "",
            "auth-token_secret" => "",
            "system-name" => "",
            "disk-name" => env("IMAGE_SERVICE_DISK_NAME", "public"),
        ],
    ],
];
