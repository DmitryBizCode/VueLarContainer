<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default disk for storing photos (public_images = public/image/, public = storage/app/public)
    |--------------------------------------------------------------------------
    */
    'disk' => 'public_images',

    /*
    |--------------------------------------------------------------------------
    | Base directory under the disk (no leading/trailing slashes)
    | All categories are stored under this path, e.g. image/containers, image/avatars
    |--------------------------------------------------------------------------
    */
    'base_path' => 'image',

    /*
    |--------------------------------------------------------------------------
    | Photo categories: subfolder name => options
    | Used by PhotoStorageService::store($file, 'containers') etc.
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'containers' => [
            'max_width' => 1200,
            'max_height' => 1200,
            'quality' => 85,
            'extension' => 'jpg',
        ],
        'avatars' => [
            'max_width' => 400,
            'max_height' => 400,
            'quality' => 88,
            'extension' => 'jpg',
        ],
        'profile' => [
            'max_width' => 400,
            'max_height' => 400,
            'quality' => 88,
            'extension' => 'jpg',
        ],
    ],

];
