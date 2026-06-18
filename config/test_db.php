<?php

$dbPath = dirname(__DIR__) . '/runtime/test.db';
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'sqlite:' . $dbPath,
    'charset' => 'utf8',
];
