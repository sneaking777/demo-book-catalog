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
            // Массивная форма (а не фабрика-callable) — контейнер сам резолвит
            // через Container::build, без рекурсии в эту же запись.
            SmsPilotClient::class => array_merge(
                ['class' => SmsPilotClient::class],
                $params['smsPilot'],
            ),
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
                    // info — чтобы успехи отправки SMS подписчикам тоже попадали
                    // в runtime/logs/app.log (см. NewBookNotifier / SmsPilotClient).
                    'levels' => ['error', 'warning', 'info'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // При изменении синхронизировать с app\controllers\SiteController::ALIASED_ACTIONS,
                // которая 301-редиректит /site/<action> на эти короткие URL.
                ''        => 'site/index',
                'login'   => 'site/login',
                'logout'  => 'site/logout',
                'about'   => 'site/about',
                'contact' => 'site/contact',
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
