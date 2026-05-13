<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель подписки гостя на новые книги конкретного автора.
 *
 * Подписка идентифицируется парой (автор, телефон). Уникальность пары
 * обеспечивается одноимённым индексом в БД и валидатором на уровне модели.
 *
 * Поле `updated_at` отсутствует намеренно: подписка не редактируется,
 * только создаётся.
 *
 * @property int $id Идентификатор подписки
 * @property int $author_id Идентификатор автора
 * @property string $phone Номер телефона в формате E.164, например +79123456789
 * @property int $created_at Дата оформления подписки (unix timestamp)
 *
 * @property-read Author $author Автор, на которого оформлена подписка
 *
 * @package app\models
 *
 * @extends ActiveRecord
 */
class Subscription extends ActiveRecord
{
    /**
     * Регулярное выражение для проверки телефона в формате E.164:
     * символ «+» и от 10 до 15 цифр.
     */
    public const PHONE_PATTERN = '/^\+\d{10,15}$/';

    /**
     * Возвращает имя таблицы БД, соответствующее модели.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%subscriptions}}';
    }

    /**
     * Возвращает список поведений модели.
     *
     * Используется только `createdAtAttribute`, поле `updatedAtAttribute`
     * отключено, так как подписка не редактируется.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
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
            [['author_id', 'phone'], 'required'],
            [['author_id'], 'integer'],
            [['author_id'], 'exist', 'targetClass' => Author::class, 'targetAttribute' => 'id'],
            [['phone'], 'string', 'max' => 20],
            [['phone'], 'trim'],
            [
                ['phone'],
                'match',
                'pattern' => self::PHONE_PATTERN,
                'message' => 'Телефон должен быть в формате E.164, например +79123456789.'
            ],
            [
                ['author_id'],
                'unique',
                'targetAttribute' => ['author_id', 'phone'],
                'message' => 'Подписка с этим номером на этого автора уже оформлена.'
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
            'author_id' => 'Автор',
            'phone' => 'Телефон',
            'created_at' => 'Оформлена',
        ];
    }

    /**
     * Связь: автор, на которого оформлена подписка.
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
