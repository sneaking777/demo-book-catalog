<?php

use yii\db\Migration;

/**
 * Миграция создаёт таблицу `authors` для хранения сведений об авторах книг.
 *
 * ФИО хранится тремя колонками (фамилия, имя, отчество) для возможности
 * сортировки и поиска по фамилии.
 *
 * @package app\migrations
 *
 * @extends Migration
 */
class m260513_201829_create_authors_table extends Migration
{
    /**
     * Имя таблицы с учётом префикса соединения.
     */
    private const TABLE = '{{%authors}}';

    /**
     * Применяет миграцию: создаёт таблицу `authors`, индекс по фамилии
     * и навешивает комментарий на саму таблицу.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->comment('Идентификатор автора'),
            'last_name' => $this->string(100)->notNull()->comment('Фамилия'),
            'first_name' => $this->string(100)->notNull()->comment('Имя'),
            'middle_name' => $this->string(100)->notNull()->comment('Отчество'),
            'created_at' => $this->integer()->notNull()->comment('Дата создания записи, unix timestamp'),
            'updated_at' => $this->integer()->notNull()->comment('Дата последнего изменения записи, unix timestamp'),
        ], $this->tableOptions());

        $this->addCommentOnTable(self::TABLE, 'Авторы книг');

        $this->createIndex('idx-authors-last_name', self::TABLE, 'last_name');
    }

    /**
     * Откатывает миграцию: удаляет таблицу `authors`.
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