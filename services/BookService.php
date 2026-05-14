<?php

declare(strict_types=1);

namespace app\services;

use app\models\Book;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * Use-case-сервис над сущностью «Книга».
 *
 * Инкапсулирует операции записи и связанные побочные эффекты
 * (рассылка SMS подписчикам автора), чтобы контроллер оставался
 * тонким: load → передать модель в сервис → render/redirect.
 *
 * Чтение и поиск (`Book::find()`, `BookSearch`) идут мимо сервиса —
 * ActiveRecord уже выступает репозиторием, отдельный слой бесполезен.
 *
 * @package app\services
 */
readonly class BookService
{
    /**
     * @param NewBookNotifier $notifier Рассыльщик SMS подписчикам автора.
     */
    public function __construct(private NewBookNotifier $notifier)
    {
    }

    /**
     * Сохраняет новую книгу и при успехе уведомляет подписчиков
     * её авторов о появлении.
     *
     * Уведомление вызывается только после успешного коммита `save()`,
     * чтобы SMS не уходило о книге, которой нет в БД. Сбои отправки
     * логируются внутри {@see NewBookNotifier} и не валят бизнес-операцию.
     *
     * @param Book $book Несохранённая модель с заполненными атрибутами.
     *
     * @return bool `true` — книга сохранена; `false` — провалилась валидация.
     *
     * @throws Exception При сбое уровня БД во время сохранения.
     */
    public function create(Book $book): bool
    {
        if (!$book->save()) {
            return false;
        }

        $this->notifier->notify($book);

        return true;
    }

    /**
     * Сохраняет изменения существующей книги.
     *
     * Уведомления подписчикам не отправляются — SMS приходит только
     * при первом появлении книги.
     *
     * @param Book $book Загруженная модель с применёнными изменениями.
     *
     * @return bool `true` — изменения сохранены; `false` — провалилась валидация.
     *
     * @throws Exception При сбое уровня БД.
     * @throws StaleObjectException Если запись изменена в параллельной транзакции (оптимистичная блокировка).
     */
    public function update(Book $book): bool
    {
        return $book->save();
    }
}
