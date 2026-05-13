<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\base\BaseObject;

/**
 * Тонкая обёртка над HTTP-API smspilot.ru (v1, JSON).
 *
 * Регистрируется как компонент Yii (см. `config/web.php`), параметры
 * берутся из `params.php` (`smsPilot.*`). Ошибки сети и API не бросаются,
 * а логируются — клиенту достаточно знать, ушло сообщение или нет.
 *
 * На тестовом ключе «эмулятор» (значение по умолчанию) реальной отправки
 * не происходит, API возвращает успешный ответ — удобно для разработки.
 *
 * @package app\services
 *
 * @extends BaseObject
 */
class SmsPilotClient extends BaseObject
{
    /**
     * Ключ API smspilot.ru. По умолчанию — тестовый «эмулятор»
     * (заполняется через DI из `params.smsPilot.apiKey`).
     */
    public string $apiKey = '';

    /**
     * Имя/номер отправителя (alpha-sender), показываемое получателю.
     */
    public string $from = 'INFORM';

    /**
     * HTTP-эндпоинт API.
     */
    public string $endpoint = 'https://smspilot.ru/api.php';

    /**
     * Тайм-аут одного запроса в секундах.
     */
    public int $timeout = 5;

    /**
     * Отправляет одно SMS-сообщение указанному получателю.
     *
     * @param string $phone Номер в формате E.164 (`+79123456789`).
     * @param string $message Текст сообщения. Латиница даёт больше символов
     *                        в одном SMS, кириллица — меньше; длинные сообщения
     *                        smspilot режет на сегменты автоматически.
     *
     * @return bool true — API подтвердил приём (на тестовом ключе всегда true
     *              при отсутствии сетевой ошибки), false — сетевая ошибка
     *              или API вернул ошибку.
     */
    public function send(string $phone, string $message): bool
    {
        $url = $this->endpoint . '?' . http_build_query([
            'apikey' => $this->apiKey,
            'send' => $message,
            'to' => $phone,
            'from' => $this->from,
            'format' => 'json',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                // Получаем тело ответа даже при HTTP 4xx/5xx — чтобы прочитать
                // структурированную ошибку API, а не молчаливо упасть.
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            Yii::warning("SMS не отправлено ($phone): нет связи с $this->endpoint", __METHOD__);
            return false;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            Yii::warning("SMS не отправлено ($phone): некорректный JSON в ответе: $response", __METHOD__);
            return false;
        }

        if (isset($data['error'])) {
            $code = $data['error']['code'] ?? '?';
            $description = $data['error']['description'] ?? 'неизвестная ошибка';
            Yii::warning("SMS не отправлено ($phone): smspilot вернул ошибку $code — $description", __METHOD__);
            return false;
        }

        return true;
    }
}
