<?php

declare(strict_types=1);

namespace app\models;

use RuntimeException;
use Yii;
use yii\base\Exception as BaseException;
use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception as DbException;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

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
 * @property int $version Версия записи для оптимистичной блокировки
 * @property int[] $authorIds Виртуальный атрибут — идентификаторы авторов книги
 * @property UploadedFile|null $coverFile Виртуальный атрибут — загружаемый файл обложки
 *
 * @property-read Author[] $authors Авторы книги (M:N через `book_authors`)
 * @property-read BookAuthor[] $bookAuthors Записи связи с авторами
 * @property-read string|null $coverUrl Публичный URL обложки или null, если её нет
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
     * Алиас директории на диске, куда сохраняются файлы обложек.
     * Должен соответствовать пути, исключённому из git в `.gitignore`,
     * и попадать под `location ^~ /uploads/` из `docker/nginx/default.conf`.
     */
    public const COVER_PATH_ALIAS = '@webroot/uploads/covers';

    /**
     * Алиас публичного URL-префикса для обложек.
     */
    public const COVER_URL_ALIAS = '@web/uploads/covers';

    /**
     * Максимально допустимый размер файла обложки в байтах (2 МБ).
     */
    public const COVER_MAX_SIZE = 2 * 1024 * 1024;

    /**
     * Разрешённые расширения файлов обложек.
     *
     * @var string[]
     */
    public const COVER_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

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
     * Виртуальный атрибут: загружаемый файл обложки.
     *
     * Подхватывается из $_FILES в {@see beforeValidate()}, валидируется
     * как изображение, записывается на диск в {@see afterSave()}.
     * После записи имя файла сохраняется в колонку `cover_image`.
     *
     * Свойство намеренно нетипизировано: Yii ActiveForm::fileInput()
     * рендерит скрытый `<input name="Book[coverFile]" value="">` рядом
     * с файловым полем, и при отправке формы без выбранного файла
     * `Model::load()` пытается присвоить пустую строку. Строгий тип
     * `?UploadedFile` это бы запретил с TypeError; нетипизированное
     * свойство принимает любое значение, а потом валидатор `file`
     * либо подменяет его на {@see UploadedFile}, либо пропускает дальше
     * как «нет файла».
     *
     * @var UploadedFile|null
     *
     * @noinspection PhpMissingFieldTypeInspection — намеренно без типа, см. описание выше
     * @noinspection PhpUnused — заполняется через Model::load() (mass-assignment из формы)
     */
    public $coverFile = null;

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
     * Включает оптимистичную блокировку: ActiveRecord будет добавлять
     * `AND version = :oldVersion` в WHERE при UPDATE и инкрементировать
     * `version` на 1. Если параллельный апдейт уже сдвинул версию —
     * текущий получит {@see \yii\db\StaleObjectException}.
     *
     * @return string Имя колонки версии.
     */
    public function optimisticLock(): string
    {
        return 'version';
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
            [['version'], 'integer'],
            [
                ['coverFile'],
                'file',
                'extensions' => self::COVER_EXTENSIONS,
                'maxSize' => self::COVER_MAX_SIZE,
                'skipOnEmpty' => true,
                'tooBig' => 'Файл обложки не должен превышать ' . (self::COVER_MAX_SIZE / 1024 / 1024) . ' МБ.',
                'wrongExtension' => 'Допустимые форматы обложки: ' . implode(', ', self::COVER_EXTENSIONS) . '.',
            ],
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
            'coverFile' => 'Обложка',
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
     * Перед валидацией подхватывает загруженный файл обложки из $_FILES
     * (если он был передан). Делается здесь, а не в контроллере, чтобы
     * логика не дублировалась между create и update.
     *
     * @return bool Можно ли продолжать валидацию.
     */
    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $instance = UploadedFile::getInstance($this, 'coverFile');
        if ($instance !== null) {
            $this->coverFile = $instance;
        }

        return true;
    }

    /**
     * Перед сохранением: если был загружен новый файл обложки, генерирует
     * для него уникальное имя и проставляет в {@see $cover_image}. Сам файл
     * записывается на диск позже в {@see afterSave()}, уже внутри транзакции.
     *
     * @param bool $insert true для insert, false для update.
     *
     * @return bool Можно ли продолжать сохранение.
     *
     * @throws BaseException Если не удалось сгенерировать случайное имя файла.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->coverFile !== null) {
            $extension = strtolower($this->coverFile->extension);
            $this->cover_image = Yii::$app->security->generateRandomString(16) . '.' . $extension;
        }

        return true;
    }

    /**
     * После сохранения книги выполняет две задачи:
     *  - приводит таблицу `book_authors` в соответствие с {@see $authorIds}
     *    одним `batchInsert`;
     *  - если был загружен новый файл обложки, записывает его на диск
     *    и удаляет старый файл (при замене).
     *
     * Всё выполняется внутри транзакции (см. {@see transactions()}) —
     * любая ошибка откатывает сохранение книги.
     *
     * @param bool $insert true для insert, false для update.
     * @param array<string, mixed> $changedAttributes Изменённые атрибуты до сохранения.
     *
     * @return void
     *
     * @throws DbException При сбое уровня БД во время delete/insert связей.
     * @throws RuntimeException При невозможности записать файл обложки на диск.
     */
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        BookAuthor::deleteAll(['book_id' => $this->id]);

        $ids = array_unique(array_map('intval', $this->authorIds));
        if ($ids !== []) {
            $rows = array_map(
                fn (int $authorId): array => [$this->id, $authorId],
                $ids,
            );

            Yii::$app->db
                ->createCommand()
                ->batchInsert('{{%book_authors}}', ['book_id', 'author_id'], $rows)
                ->execute();
        }

        if ($this->coverFile !== null) {
            if (!$this->coverFile->saveAs(self::coverPath($this->cover_image))) {
                throw new RuntimeException('Не удалось сохранить файл обложки на диск.');
            }

            $oldFilename = $changedAttributes['cover_image'] ?? null;
            if (is_string($oldFilename) && $oldFilename !== '' && $oldFilename !== $this->cover_image) {
                $this->deleteCoverFile($oldFilename);
            }

            $this->coverFile = null;
        }
    }

    /**
     * После удаления книги удаляет связанный файл обложки с диска.
     * Записи в `book_authors` и `subscriptions` (если бы они были связаны)
     * чистятся каскадом на уровне БД.
     *
     * @return void
     */
    public function afterDelete(): void
    {
        parent::afterDelete();

        if (is_string($this->cover_image) && $this->cover_image !== '') {
            $this->deleteCoverFile($this->cover_image);
        }
    }

    /**
     * Удаляет файл обложки с диска. Ошибки игнорируются (логируются),
     * чтобы отсутствующий файл не блокировал бизнес-операцию.
     *
     * @param string $filename Имя файла внутри каталога обложек.
     *
     * @return void
     */
    private function deleteCoverFile(string $filename): void
    {
        $path = self::coverPath($filename);
        if (is_file($path) && !@unlink($path)) {
            Yii::warning("Не удалось удалить файл обложки: $path", __METHOD__);
        }
    }

    /**
     * Возвращает абсолютный путь к каталогу, в котором хранятся файлы обложек.
     *
     * @return string Абсолютный путь без завершающего слэша.
     */
    public static function coverDir(): string
    {
        return Yii::getAlias(self::COVER_PATH_ALIAS);
    }

    /**
     * Строит абсолютный путь к файлу обложки на диске.
     *
     * Public static, чтобы той же логикой могли пользоваться внешние
     * утилиты — например, консольный сидер ({@see \app\commands\SeedController}) —
     * и при изменении схемы хранения обложек не пришлось править два места.
     *
     * @param string $filename Имя файла внутри каталога обложек.
     *
     * @return string Абсолютный путь.
     */
    public static function coverPath(string $filename): string
    {
        return self::coverDir() . '/' . $filename;
    }

    /**
     * Возвращает публичный URL обложки или null, если её нет.
     *
     * @return string|null
     *
     * @noinspection PhpUnused — вызывается магически через ActiveRecord::__get() как $book->coverUrl
     */
    public function getCoverUrl(): ?string
    {
        if (!is_string($this->cover_image) || $this->cover_image === '') {
            return null;
        }

        return Yii::getAlias(self::COVER_URL_ALIAS) . '/' . $this->cover_image;
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
