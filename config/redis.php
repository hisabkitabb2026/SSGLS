<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redis Client Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls the client used to communicate with Redis and
    | which Redis 'connection' is used by default. Redis is a fast, fully
    | featured caching backend for Laravel, so it's the perfect choice
    | for accelerating your application's performance.
    |
    */

    'client' => env('REDIS_CLIENT', 'predis'),

    /*
    |--------------------------------------------------------------------------
    | Redis Cluster
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Redis cluster if you are using a set of
    | Redis servers operating in cluster mode. Your applications will need
    | a bit more configuration in each of these environments.
    |
    */

    'clusters' => [

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'timeout' => env('REDIS_CLUSTER_TIMEOUT', 0),
            'persistent' => env('REDIS_CLUSTER_PERSISTENT', 0),
            'protocol' => 'tcp',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the Redis connections used by the application. A
    | default configuration has been included, but you may add as many
    | additional connections as you would like.
    |
    */

    'connections' => [

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
            'read_timeout' => 0,
            'connection_pool' => false,
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
            'read_timeout' => 0,
            'connection_pool' => false,
        ],

        'queue' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_QUEUE_DB', 2),
            'read_timeout' => 0,
            'connection_pool' => false,
        ],

        'session' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_SESSION_DB', 3),
            'read_timeout' => 0,
            'connection_pool' => false,
        ],

    ],

];
