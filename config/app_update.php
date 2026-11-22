<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile App Release Check
    |--------------------------------------------------------------------------
    |
    | The mobile app calls GET /api/app/version with its platform, version and
    | build number. The latest published build for each store is configured
    | here so an update can be offered (or enforced) without shipping code.
    |
    */

    'android' => [
        'version' => env('ANDROID_APP_VERSION', '1.0.0'),
        'build' => (int) env('ANDROID_APP_BUILD', 0),
        'store_url' => env('ANDROID_STORE_URL', ''),
    ],

    'ios' => [
        'version' => env('IOS_APP_VERSION', '1.0.0'),
        'build' => (int) env('IOS_APP_BUILD', 0),
        'store_url' => env('IOS_APP_STORE_URL', ''),
    ],

    'required' => filter_var(env('UPDATE_REQUIRED', false), FILTER_VALIDATE_BOOLEAN),

    'message' => env('APP_UPDATE_MESSAGE', ''),

    'remind_later_hours' => (int) env('UPDATE_REMIND_LATER_HOURS', 24),

];
