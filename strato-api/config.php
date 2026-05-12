<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => 'database-5020340144.webspace-host.com',
        'port' => 3306,
        'name' => 'dbs15619002',
        'user' => 'dbu5132826',
        'pass' => 'Nicecult2026!',
        'charset' => 'utf8mb4',
    ],
    'jwt' => [
        'secret' => '5a2782cc9bcdde81a1ac79c61eb5692796ef9fec76f7879f6f5c51c37a1be411eebe32342ad8ade6780e909fbbc9560e62d58437df4f4325346c770861579ba1',
        'issuer' => 'nichecult-api',
        'ttl_seconds' => 60 * 60 * 24 * 7,
    ],
    'cors' => [
        'allow_origins' => [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:3002',
            'http://127.0.0.1:3002',
            'http://nichecult.de',
            'http://www.nichecult.de',
            'https://nichecult.de',
            'https://www.nichecult.de',
            'https://nichecult.netlify.app',
        ],
    ],
];
