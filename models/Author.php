<?php

declare(strict_types=1);

namespace app\models;

use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель автора книги.
 *
 * ФИО хранится тремя колонками. Метаданные `created_at` / `updated_at`
 * заполняются автоматически через {@see TimestampBehavior}.
 *
 * @property int $id Идентификатор автора
 * @property string $last_name Фамилия
 * @property string $first_name Имя
 * @property string $middle_name Отчество
 * @property int $created_at Дата создания записи (unix timestamp)
 * @property int $updated_at Дата последнего изменения записи (unix timestamp)
 *
 * @property-read Book[] $books Книги, написанные автором (M:N через `book_authors`)
 * @property-read BookAuthor[] $bookAuthors Записи связи с книгами
 * @property-read Subscription[] $subscriptions Подписки гостей на этого автора
 * @property-read string $fullName Полное ФИО одной строкой
 *
 * @package app\models
 *
 * @extends ActiveRecord
 */
class Author extends ActiveRecord
{
    /**
     * Возвращает имя таблицы БД, соответствующее модели.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%authors}}';
    }

    /**
     * Возвращает список поведений модели.
     *
     * `TimestampBehavior` автоматически заполняет поля `created_at`
     * и `updated_at` текущим unix timestamp при insert/update.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * Возвращает правила валидации атрибутов модели.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['last_name', 'first_name', 'middle_name'], 'required'],
            [['last_name', 'first_name', 'middle_name'], 'string', 'max' => 100],
            [['last_name', 'first_name', 'middle_name'], 'trim'],
        ];
    }

    /**
     * Возвращает человекочитаемые названия атрибутов для форм и сообщений.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'last_name' => 'Фамилия',
            'first_name' => 'Имя',
            'middle_name' => 'Отчество',
            'created_at' => 'Создан',
            'updated_at' => 'Изменён',
            'fullName' => 'ФИО',
        ];
    }

    /**
     * Связь: книги автора (M:N через таблицу `book_authors`).
     *
     * @return ActiveQuery
     *
     * @throws InvalidConfigException
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getBooks(): ActiveQuery
    {
        return $this->hasMany(Book::class, ['id' => 'book_id'])
            ->viaTable('{{%book_authors}}', ['author_id' => 'id']);
    }

    /**
     * Связь: записи связки с книгами (для прямого доступа к pivot-строкам).
     *
     * @return ActiveQuery
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getBookAuthors(): ActiveQuery
    {
        return $this->hasMany(BookAuthor::class, ['author_id' => 'id']);
    }

    /**
     * Связь: подписки гостей на этого автора.
     *
     * @return ActiveQuery
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getSubscriptions(): ActiveQuery
    {
        return $this->hasMany(Subscription::class, ['author_id' => 'id']);
    }

    /**
     * Возвращает ФИО автора в формате «Фамилия Имя Отчество».
     *
     * @return string
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get() как $author->fullName
     */
    public function getFullName(): string
    {
        return trim("$this->last_name $this->first_name $this->middle_name");
    }
}
