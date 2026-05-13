<?php

use yii\db\Migration;

/**
 * Добавляет колонку `version` в таблицу `books` для оптимистичной блокировки.
 *
 * `version` инкрементируется при каждом update книги. Если параллельно
 * два пользователя редактируют одну книгу — второй получит `StaleObjectException`
 * и узнает, что данные были изменены, вместо того чтобы молча затереть
 * чужие правки.
 *
 * @package app\migrations
 *
 * @extends Migration
 */
class m260513_223352_add_version_to_books_table extends Migration
{
    /**
     * Имя таблицы с учётом префикса соединения.
     */
    private const TABLE = '{{%books}}';

    /**
     * Применяет миграцию: добавляет колонку `version`.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->addColumn(
            self::TABLE,
            'version',
            $this->integer()->unsigned()->notNull()->defaultValue(0)
                ->comment('Версия записи для оптимистичной блокировки'),
        );
    }

    /**
     * Откатывает миграцию: удаляет колонку `version`.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->dropColumn(self::TABLE, 'version');
    }
}