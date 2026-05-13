<?php

use yii\db\Migration;

/**
 * Миграция создаёт таблицу `books` для хранения сведений о книгах каталога.
 *
 * Связь книг с авторами оформляется отдельной таблицей `book_authors` (M:N),
 * она будет создана следующей миграцией.
 *
 * Изображение обложки хранится как имя файла; сами файлы лежат в `web/uploads/`
 * и отдаются nginx напрямую.
 *
 * @package app\migrations
 *
 * @extends Migration
 */
class m260513_204014_create_books_table extends Migration
{
    /**
     * Имя таблицы с учётом префикса соединения.
     */
    private const TABLE = '{{%books}}';

    /**
     * Применяет миграцию: создаёт таблицу `books`, уникальный индекс по ISBN,
     * индекс по году издания и навешивает комментарий на таблицу.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->comment('Идентификатор книги'),
            'title' => $this->string(255)->notNull()->comment('Название книги'),
            'year' => $this->smallInteger()->notNull()->comment('Год издания'),
            'description' => $this->text()->null()->comment('Краткое описание книги'),
            'isbn' => $this->string(20)->notNull()->comment('Международный стандартный книжный номер (ISBN-10 или ISBN-13)'),
            'cover_image' => $this->string(255)->null()->comment('Имя файла обложки в каталоге web/uploads'),
            'created_at' => $this->integer()->notNull()->comment('Дата создания записи, unix timestamp'),
            'updated_at' => $this->integer()->notNull()->comment('Дата последнего изменения записи, unix timestamp'),
        ], $this->tableOptions());

        $this->addCommentOnTable(self::TABLE, 'Книги каталога');

        $this->createIndex('uniq-books-isbn', self::TABLE, 'isbn', true);
        $this->createIndex('idx-books-year', self::TABLE, 'year');
    }

    /**
     * Откатывает миграцию: удаляет таблицу `books`.
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