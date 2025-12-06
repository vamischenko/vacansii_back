<?php

/**
 * Конфигурация подключения к базе данных
 *
 * Использует переменные окружения для безопасного хранения credentials.
 * Параметры загружаются из .env файла.
 */

return [
    'class' => 'yii\db\Connection',
    'dsn' => getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=vakansii_db',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',

    // Schema cache включен для production окружения
    'enableSchemaCache' => YII_ENV_PROD,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
