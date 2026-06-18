<?php

declare(strict_types=1);

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/tests/_support/TestSchema.php';

Yii::setAlias('@app', __DIR__);
Yii::setAlias('@webroot', __DIR__ . '/web');
Yii::setAlias('@web', '/');
Yii::setAlias('@runtime', __DIR__ . '/runtime');
Yii::setAlias('@vendor', __DIR__ . '/vendor');
Yii::setAlias('@bower', __DIR__ . '/vendor/bower-asset');
Yii::setAlias('@npm', __DIR__ . '/vendor/npm-asset');
