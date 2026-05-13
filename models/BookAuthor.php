<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель связки «книга — автор» (pivot для отношения M:N).
 *
 * Соответствует одной строке в таблице `book_authors`. Первичный ключ
 * составной: (book_id, author_id), поэтому модель используется
 * преимущественно для прямого управления связями (вставка/удаление),
 * а не для самостоятельного существования.
 *
 * @property int $book_id Идентификатор книги
 * @property int $author_id Идентификатор автора
 *
 * @property-read Book $book Книга
 * @property-read Author $author Автор
 *
 * @package app\models
 *
 * @extends ActiveRecord
 */
class BookAuthor extends ActiveRecord
{
    /**
     * Возвращает имя таблицы БД, соответствующее модели.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%book_authors}}';
    }

    /**
     * Возвращает определение составного первичного ключа таблицы.
     *
     * Yii по умолчанию ожидает одиночный PK с именем `id`; для pivot-таблиц
     * с составным PK его нужно объявить вручную.
     *
     * @return array<int, string>
     */
    public static function primaryKey(): array
    {
        return ['book_id', 'author_id'];
    }

    /**
     * Возвращает правила валидации атрибутов модели.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['book_id', 'author_id'], 'required'],
            [['book_id', 'author_id'], 'integer'],
            [['book_id'], 'exist', 'targetClass' => Book::class, 'targetAttribute' => 'id'],
            [['author_id'], 'exist', 'targetClass' => Author::class, 'targetAttribute' => 'id'],
            [['book_id', 'author_id'], 'unique', 'targetAttribute' => ['book_id', 'author_id']],
        ];
    }

    /**
     * Связь: книга, к которой относится pivot-строка.
     *
     * @return ActiveQuery
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getBook(): ActiveQuery
    {
        return $this->hasOne(Book::class, ['id' => 'book_id']);
    }

    /**
     * Связь: автор, к которому относится pivot-строка.
     *
     * @return ActiveQuery
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getAuthor(): ActiveQuery
    {
        return $this->hasOne(Author::class, ['id' => 'author_id']);
    }
}