<?php

return [
    'db_host' => getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('MYSQLDATABASE') ?: getenv('DB_DATABASE') ?: 'store_app',
    'db_user' => getenv('MYSQLUSER') ?: getenv('DB_USERNAME') ?: 'root',
    'db_pass' => getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '',
    'base_url' => getenv('APP_URL') ?: 'https://example.com/api',
    'upload_dir' => getenv('UPLOAD_DIR') ?: __DIR__ . '/uploads',
];
