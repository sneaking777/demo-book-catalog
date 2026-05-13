<?php

declare(strict_types=1);

namespace app\models;

use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель книги каталога.
 *
 * Книга может быть написана несколькими авторами (связь M:N через
 * `book_authors`). ISBN уникален в пределах таблицы.
 *
 * Метаданные `created_at` / `updated_at` заполняются автоматически
 * через {@see TimestampBehavior}.
 *
 * @property int $id Идентификатор книги
 * @property string $title Название
 * @property int $year Год издания
 * @property string|null $description Краткое описание
 * @property string $isbn ISBN-10 или ISBN-13
 * @property string|null $cover_image Имя файла обложки в каталоге `web/uploads`
 * @property int $created_at Дата создания записи (unix timestamp)
 * @property int $updated_at Дата последнего изменения записи (unix timestamp)
 *
 * @property-read Author[] $authors Авторы книги (M:N через `book_authors`)
 * @property-read BookAuthor[] $bookAuthors Записи связи с авторами
 *
 * @package app\models
 *
 * @extends ActiveRecord
 */
class Book extends ActiveRecord
{
    /**
     * Минимально допустимый год издания (для валидации).
     */
    public const MIN_YEAR = 1450;

    /**
     * Возвращает имя таблицы БД, соответствующее модели.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%books}}';
    }

    /**
     * Возвращает список поведений модели.
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
            [['title', 'year', 'isbn'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['cover_image'], 'string', 'max' => 255],
            [['isbn'], 'string', 'max' => 20],
            [['isbn'], 'unique'],
            [['isbn'], 'match', 'pattern' => '/^[0-9\-Xx]{10,20}$/', 'message' => 'ISBN должен содержать только цифры, дефисы и X.'],
            [['year'], 'integer', 'min' => self::MIN_YEAR, 'max' => 9999],
            [['title', 'description', 'isbn', 'cover_image'], 'trim'],
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
            'title' => 'Название',
            'year' => 'Год издания',
            'description' => 'Описание',
            'isbn' => 'ISBN',
            'cover_image' => 'Обложка',
            'created_at' => 'Создана',
            'updated_at' => 'Изменена',
        ];
    }

    /**
     * Связь: авторы книги (M:N через таблицу `book_authors`).
     *
     * @return ActiveQuery
     *
     * @throws InvalidConfigException
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getAuthors(): ActiveQuery
    {
        return $this->hasMany(Author::class, ['id' => 'author_id'])
            ->viaTable('{{%book_authors}}', ['book_id' => 'id']);
    }

    /**
     * Связь: записи связки с авторами (для прямого доступа к pivot-строкам).
     *
     * @return ActiveQuery
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get()
     */
    public function getBookAuthors(): ActiveQuery
    {
        return $this->hasMany(BookAuthor::class, ['book_id' => 'id']);
    }
}
