<?php

use yii\db\Migration;

/**
 * Миграция создаёт таблицу `subscriptions` для хранения подписок гостей
 * на появление новых книг конкретного автора.
 *
 * Подписка идентифицируется парой (автор, номер телефона). Уникальный
 * индекс по этой паре исключает повторные подписки на одного автора
 * с того же номера.
 *
 * При удалении автора связанные подписки удаляются каскадом.
 *
 * @package app\migrations
 *
 * @extends Migration
 */
class m260513_205507_create_subscriptions_table extends Migration
{
    /**
     * Имя таблицы с учётом префикса соединения.
     */
    private const TABLE = '{{%subscriptions}}';

    /**
     * Имя таблицы авторов (для построения внешнего ключа).
     */
    private const AUTHORS = '{{%authors}}';

    /**
     * Применяет миграцию: создаёт таблицу `subscriptions`, уникальный
     * индекс по паре (author_id, phone) и внешний ключ на `authors`.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->comment('Идентификатор подписки'),
            'author_id' => $this->integer()->notNull()->comment('Идентификатор автора, на которого оформлена подписка'),
            'phone' => $this->string(20)->notNull()->comment('Номер телефона подписчика в формате E.164, например +79123456789'),
            'created_at' => $this->integer()->notNull()->comment('Дата оформления подписки, unix timestamp'),
        ], $this->tableOptions());

        $this->addCommentOnTable(self::TABLE, 'Подписки гостей на новые книги конкретного автора');

        $this->createIndex('uniq-subscriptions-author_id-phone', self::TABLE, ['author_id', 'phone'], true);

        $this->addForeignKey(
            'fk-subscriptions-author_id',
            self::TABLE,
            'author_id',
            self::AUTHORS,
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    /**
     * Откатывает миграцию: удаляет таблицу `subscriptions`
     * (вместе с ней пропадают внешний ключ и индексы).
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->dropTable(self::TABLE);
    }

    /**
     * Возвращает опции создания таблицы для MySQL (кодировка, движок).
     *
     * @return string|null Строка опций для MySQL или null для прочих СУБД.
     */
    private function tableOptions(): ?string
    {
        if ($this->db->driverName === 'mysql') {
            return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        return null;
    }
}