<?php

return [

    /*
    |--------------------------------------------------------------------------
    | REST Countries API
    |--------------------------------------------------------------------------
    |
    | Countries are pulled from the REST Countries API (v5). Every request
    | needs an API key: get one at https://restcountries.com/sign-up and set
    | COUNTRY_API_KEY in your .env file.
    |
    */

    'api' => [
        'base_url' => env('COUNTRY_API_URL', 'https://api.restcountries.com/countries/v5'),

        'key' => env('COUNTRY_API_KEY'),

        // Request timeout, in seconds.
        'timeout' => (int) env('COUNTRY_API_TIMEOUT', 15),

        // Retries on connection errors, 429 (rate limit) and 5xx responses.
        'retry' => [
            'times' => 3,
            'sleep' => 1000, // milliseconds
        ],

        // Page size used to walk the country list (1-100 on the free plan).
        'per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'table' => 'countries',

    // Swap in your own model (it should extend the package model).
    'model' => Cpuchalver\LaravelCountry\Models\Country::class,

    // Load the package migration automatically. Set to false if you publish
    // the migration with `php artisan vendor:publish --tag=country-migrations`.
    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    */

    'sync' => [
        // Delete local countries that no longer exist in the API on a full sync.
        'prune' => false,
    ],

];
