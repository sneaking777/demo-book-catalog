<?php

declare(strict_types=1);

namespace app\services;

use app\models\Book;
use yii\db\Query;
use yii\helpers\Url;

/**
 * Рассылает уведомления подписчикам о появлении новой книги.
 *
 * Соответствие подписчиков и книги: подписчик подписан на автора →
 * получает уведомление о КАЖДОЙ новой книге этого автора. Если у книги
 * несколько авторов и человек подписан на нескольких из них, он получит
 * только одно сообщение (телефоны разыменовываются через DISTINCT).
 *
 * Сам класс не знает деталей конкретного SMS-провайдера — общается
 * через {@see SmsPilotClient}.
 *
 * @package app\services
 */
readonly class NewBookNotifier
{
    /**
     * @param SmsPilotClient $smsClient Клиент SMS-провайдера.
     */
    public function __construct(private SmsPilotClient $smsClient)
    {
    }

    /**
     * Уведомляет всех подписчиков о появлении книги. Должен вызываться
     * **после** успешного сохранения книги в БД (после коммита транзакции),
     * чтобы не отправить SMS о книге, которой нет.
     *
     * @param Book $book Свежесозданная книга.
     *
     * @return int Количество SMS, принятых API (не обязательно доставленных).
     */
    public function notify(Book $book): int
    {
        $phones = $this->collectPhones($book);
        if ($phones === []) {
            return 0;
        }

        $message = $this->buildMessage($book);
        $sent = 0;
        foreach ($phones as $phone) {
            if ($this->smsClient->send($phone, $message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Возвращает уникальные телефоны всех подписчиков, подписанных
     * хоть на одного из авторов этой книги.
     *
     * SQL: один запрос с DISTINCT + INNER JOIN, использует индексы
     * `idx-book_authors-author_id` (PK как fallback) и
     * `uniq-subscriptions-author_id-phone` (через author_id).
     *
     * @param Book $book Книга.
     *
     * @return string[] Список телефонов в формате E.164.
     */
    private function collectPhones(Book $book): array
    {
        return (new Query())
            ->select('s.phone')
            ->distinct()
            ->from(['s' => '{{%subscriptions}}'])
            ->innerJoin(['ba' => '{{%book_authors}}'], 'ba.author_id = s.author_id')
            ->where(['ba.book_id' => $book->id])
            ->column();
    }

    /**
     * Формирует короткий текст SMS с названием книги, авторами
     * и абсолютной ссылкой на карточку. Длина сообщения — компромисс:
     * чем короче, тем дешевле (один SMS на кириллице ≈ 70 символов).
     *
     * @param Book $book Книга.
     *
     * @return string Текст для отправки.
     */
    private function buildMessage(Book $book): string
    {
        $authors = implode(', ', array_map(
            static fn ($a) => $a->fullName,
            $book->authors,
        ));

        $url = Url::to(['/book/view', 'id' => $book->id], true);

        return sprintf('Новая книга «%s» — %s. Подробнее: %s', $book->title, $authors, $url);
    }
}
