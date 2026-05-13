<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception as DbException;
use yii\helpers\ArrayHelper;

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
 * @property int[] $authorIds Виртуальный атрибут — идентификаторы авторов книги
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
     * Виртуальный атрибут: идентификаторы авторов книги.
     *
     * Не привязан к колонке в таблице `books`. Заполняется формой,
     * валидируется в {@see rules()}, синхронизируется с таблицей
     * `book_authors` в {@see afterSave()} и подгружается в {@see afterFind()}.
     *
     * @var int[]
     */
    public array $authorIds = [];

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
            [['title', 'year', 'isbn', 'authorIds'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['cover_image'], 'string', 'max' => 255],
            [['isbn'], 'string', 'max' => 20],
            [['isbn'], 'unique'],
            [['isbn'], 'match', 'pattern' => '/^[0-9\-Xx]{10,20}$/', 'message' => 'ISBN должен содержать только цифры, дефисы и X.'],
            [['year'], 'integer', 'min' => self::MIN_YEAR, 'max' => 9999],
            [['title', 'description', 'isbn', 'cover_image'], 'trim'],
            [['authorIds'], 'each', 'rule' => ['integer']],
            [['authorIds'], 'validateAuthorsExist'],
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
            'authorIds' => 'Авторы',
        ];
    }

    /**
     * Инлайн-валидатор: проверяет, что все идентификаторы из {@see $authorIds}
     * существуют в таблице `authors`. Делает один SELECT COUNT(*) вместо
     * N отдельных запросов, как у валидатора `each` + `exist`.
     *
     * @param string $attribute Имя проверяемого атрибута.
     * @param array<string, mixed>|null $params Дополнительные параметры правила (не используются, но обязательны по контракту валидатора).
     *
     * @return void
     *
     * @noinspection PhpUnused — вызывается Yii-валидатором по имени из rules()
     * @noinspection PhpUnusedParameterInspection — параметр обязателен по контракту inline-валидатора
     */
    public function validateAuthorsExist(string $attribute, array|null $params): void
    {
        $ids = array_unique(array_map('intval', $this->authorIds));
        if ($ids === []) {
            return;
        }

        $found = (int) Author::find()->where(['id' => $ids])->count();

        if ($found !== count($ids)) {
            $this->addError($attribute, 'Один или несколько выбранных авторов не существуют.');
        }
    }

    /**
     * Указывает Yii, какие операции выполнять внутри транзакции.
     *
     * Для `insert`/`update` это критично: после `save()` мы вручную
     * синхронизируем `book_authors` в {@see afterSave()}, и если эта
     * синхронизация упадёт — транзакция откатит сохранение самой книги.
     *
     * @return array<string, int>
     */
    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_INSERT | self::OP_UPDATE,
        ];
    }

    /**
     * После загрузки модели заполняет {@see $authorIds} текущими
     * идентификаторами авторов из связки M:N. Нужно для отображения
     * предзаполненного мульти-селекта в форме редактирования.
     *
     * @return void
     */
    public function afterFind(): void
    {
        parent::afterFind();

        $this->authorIds = ArrayHelper::getColumn($this->authors, 'id');
    }

    /**
     * После сохранения книги приводит таблицу `book_authors` в соответствие
     * со значением {@see $authorIds}: удаляет старые связи, вставляет
     * текущий набор одним batch INSERT. Выполняется внутри транзакции
     * (см. {@see transactions()}) — любая ошибка откатывает сохранение книги.
     *
     * @param bool $insert true для insert, false для update.
     * @param array<string, mixed> $changedAttributes Изменённые атрибуты до сохранения.
     *
     * @return void
     *
     * @throws DbException При сбое уровня БД во время delete/insert связей.
     */
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        BookAuthor::deleteAll(['book_id' => $this->id]);

        $ids = array_unique(array_map('intval', $this->authorIds));
        if ($ids === []) {
            return;
        }

        $rows = array_map(
            fn (int $authorId): array => [$this->id, $authorId],
            $ids,
        );

        Yii::$app->db
            ->createCommand()
            ->batchInsert('{{%book_authors}}', ['book_id', 'author_id'], $rows)
            ->execute();
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
