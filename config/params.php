<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'smsPilot' => [
        // По умолчанию — тестовый ключ smspilot.ru («эмулятор»):
        // сообщения принимаются API, но реально не отправляются.
        // Для прода переопределить через переменную окружения SMS_PILOT_API_KEY.
        'apiKey' => getenv('SMS_PILOT_API_KEY') ?: 'XXXXXXXXXXXXYYYYYYYYYYYYZZZZZZZZ',
        // Имя отправителя (alpha-sender). На тестовом ключе игнорируется.
        'from' => getenv('SMS_PILOT_FROM') ?: 'INFORM',
        // HTTP-эндпоинт API v1.
        'endpoint' => 'https://smspilot.ru/api.php',
        // Тайм-аут запроса в секундах — чтобы создание книги не зависало,
        // если smspilot.ru недоступен.
        'timeout' => 5,
    ],
];
