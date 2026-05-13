<?php

use app\models\User;
use app\services\SmsPilotClient;
use yii\caching\FileCache;
use yii\debug\Module;
use yii\log\FileTarget;
use yii\mail\MailerInterface;
use yii\symfonymailer\Mailer;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'name' => 'Каталог книг',
    'language' => 'ru-RU',
    'sourceLanguage' => 'en-US',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'container' => [
        'singletons' => [
            MailerInterface::class => [
                'class' => Mailer::class,
                // send all mails to a file by default.
                'useFileTransport' => true,
                'viewPath' => '@app/mail',
            ],
            SmsPilotClient::class => static fn () => Yii::createObject(array_merge(
                ['class' => SmsPilotClient::class],
                Yii::$app->params['smsPilot'],
            )),
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'AZ0ttaNlT5yut7kdgSagk5NFYR0zs5S6',
        ],
        'cache' => [
            'class' => FileCache::class,
        ],
        'user' => [
            'identityClass' => User::class,
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' =>  Module::class,
        // Разрешаем доступ из локальных и приватных подсетей (нужно для запросов изнутри docker-сети).
        'allowedIPs' => ['127.0.0.1', '::1', '172.16.0.0/12', '192.168.0.0/16', '10.0.0.0/8'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // Разрешаем доступ из локальных и приватных подсетей (nginx-контейнер обращается к app через docker bridge).
        'allowedIPs' => ['127.0.0.1', '::1', '172.16.0.0/12', '192.168.0.0/16', '10.0.0.0/8'],
    ];
}

return $config;
