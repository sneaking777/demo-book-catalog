<?php

use yii\db\Connection;

return [
    'class' => Connection::class,
    'dsn' => 'mysql:host=' . (getenv('DB_HOST') ?: 'db')
        . ';port=' . (getenv('DB_PORT') ?: '3306')
        . ';dbname=' . (getenv('DB_NAME') ?: 'book_catalog'),
    'username' => getenv('DB_USER') ?: 'book_catalog',
    'password' => getenv('DB_PASSWORD') ?: 'book_catalog',
    'charset' => 'utf8mb4',

    'enableSchemaCache' => YII_ENV_PROD,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',

    'attributes' => [
        PDO::ATTR_EMULATE_PREPARES => false,
    ],

];
