<?php

declare(strict_types=1);

namespace app\services;

use app\models\Subscription;
use yii\db\Exception;

/**
 * Use-case-сервис над сущностью «Подписка гостя на автора».
 *
 * Сейчас операция тривиальна (просто `save()`), но точка входа
 * вынесена отдельно, чтобы будущие шаги — отправка welcome-SMS,
 * аудит, антифрод — не растекались по контроллеру.
 *
 * @package app\services
 */
readonly class SubscriptionService
{
    /**
     * Оформляет подписку гостя на автора.
     *
     * @param Subscription $model Несохранённая модель с заполненными `author_id` и `phone`.
     *
     * @return bool `true` — подписка оформлена; `false` — провалилась валидация (см. `$model->errors`).
     *
     * @throws Exception При сбое уровня БД.
     */
    public function subscribe(Subscription $model): bool
    {
        return $model->save();
    }
}
