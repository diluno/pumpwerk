<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Page components live in resources/js/Pages (capital P), not the package
    | default js/pages. Tests use this to check that a rendered component exists.
    |
    */

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['vue'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

];
