<?php

return [
    //default adapter for questionsets
    "default" => "imageservice",

    "adapters" => [

        "imageservice" => [
            "handler" => \Cerpus\ImageServiceClient\Adapters\ImageServiceAdapter::class,
            "base-url" => "",
            "auth-client" => "none",
            "auth-url" => "",
            "auth-user" => "",
            "auth-secret" => "",
            "auth-token" => "",
            "auth-token_secret" => "",
            "system-name" => "",
        ],

    ],
];
