<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Subscription;
use app\services\SubscriptionService;
use Yii;
use yii\base\Module;
use yii\db\Exception;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер подписок гостей на новые книги конкретного автора.
 *
 * Доступен всем пользователям (включая гостей), задача явно описывает
 * оформление подписки именно гостем. Поведение единое: один POST-экшен
 * с PRG-редиректом на карточку автора и flash-сообщением о результате.
 *
 * @package app\controllers
 *
 * @extends Controller
 *
 * @noinspection PhpUnused — инстанцируется Yii-роутером по имени из URL
 */
class SubscriptionController extends Controller
{
    /**
     * @param string $id Идентификатор контроллера.
     * @param Module $module Модуль, в котором живёт контроллер.
     * @param SubscriptionService $subscriptions Сервис оформления подписок.
     * @param array<string, mixed> $config Доп. конфиг, прокидывается в `Component::__construct`.
     */
    public function __construct(
        $id,
        $module,
        private readonly SubscriptionService $subscriptions,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Возвращает список поведений контроллера.
     *
     * `VerbFilter` ограничивает оформление подписки методом POST,
     * чтобы её нельзя было создать обычной ссылкой/GET-запросом.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::class,
                    'actions' => [
                        'create' => ['POST'],
                    ],
                ],
            ],
        );
    }

    /**
     * Оформляет подписку гостя на автора. Поля приходят из формы
     * на карточке автора: `Subscription[author_id]` и `Subscription[phone]`.
     *
     * Использует Post/Redirect/Get: при любом исходе делает редирект
     * на карточку автора с flash-сообщением. Это упрощает рендеринг —
     * страница автора всегда отрисовывается чистой, без застрявших
     * данных формы в адресной строке.
     *
     * @return Response Редирект на карточку автора (или на список,
     *                  если автор не был передан).
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     * @throws Exception
     */
    public function actionCreate(): Response
    {
        $model = new Subscription();
        $session = Yii::$app->session;

        if (!$model->load(Yii::$app->request->post())) {
            $session->setFlash('error', 'Не удалось обработать форму подписки.');
            return $this->redirect(['/author/index']);
        }

        $authorId = $model->author_id;

        if ($this->subscriptions->subscribe($model)) {
            $session->setFlash(
                'success',
                'Вы подписаны на новые книги этого автора. SMS придёт на указанный номер.',
            );
        } else {
            $session->setFlash('error', implode(' ', $model->getFirstErrors()));
        }

        return $authorId > 0
            ? $this->redirect(['/author/view', 'id' => $authorId])
            : $this->redirect(['/author/index']);
    }
}
