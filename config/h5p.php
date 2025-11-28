<?php

declare(strict_types=1);

return [
    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=h5p_service;charset=utf8mb4',
        'user'     => 'root',
        'password' => '',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'paths' => [
        'content'    => __DIR__ . '/../storage/h5p/content',
        'temp'       => __DIR__ . '/../storage/h5p/temp',
        'editor_tmp' => __DIR__ . '/../storage/h5p/editor_tmp',
        'libraries'  => __DIR__ . '/../storage/h5p/libraries',
    ],
    'urls' => [
        'base'    => 'http://localhost:8080',
        // ...
    ],

    'urls' => [
        // Base de tu microservicio (como lo ve .NET vía iframe)
        'base'          => 'http://localhost:8080',
        // URL pública donde servirás assets H5P
        'h5p'           => 'http://localhost:8080/h5p', // luego veremos cómo exponer esto
        'libraries_url' => 'http://localhost:8080/h5p/libraries',
    ],
];
