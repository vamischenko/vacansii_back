<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Конфигурация приложения для тестов (unit, functional, API).
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'testDb'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'container' => [
        'singletons' => [
            \app\repositories\VacancyRepositoryInterface::class => \app\repositories\VacancyRepository::class,
        ],
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        'cache' => [
            'class' => 'yii\caching\ArrayCache',
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            'baseUrl' => '',
            'scriptUrl' => '/index-test.php',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'response' => [
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
            'messageClass' => 'yii\symfonymailer\Message',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'baseUrl' => '',
            'rules' => [
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'vacancy',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET' => 'index',
                        'GET search' => 'search',
                        'GET {id}' => 'view',
                        'POST' => 'create',
                    ],
                ],
            ],
        ],
        'user' => [
            'identityClass' => 'app\models\User',
        ],
        'testDb' => [
            'class' => 'app\components\TestDbBootstrap',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
    'params' => $params,
];
