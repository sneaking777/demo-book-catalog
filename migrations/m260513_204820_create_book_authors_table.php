<?php

use yii\db\Migration;

/**
 * Миграция создаёт связующую таблицу `book_authors` для отношения M:N
 * между книгами и авторами.
 *
 * Первичный ключ — составной из (book_id, author_id), что исключает
 * дублирование связи «одна книга — один автор». Дополнительный индекс
 * по author_id ускоряет обратный обход (книги конкретного автора),
 * по book_id отдельный индекс не нужен — он покрывается составным PK.
 *
 * Удаление родителя (книги или автора) каскадно удаляет связи.
 *
 * @package app\migrations
 *
 * @extends Migration
 */
class m260513_204820_create_book_authors_table extends Migration
{
    /**
     * Имя связующей таблицы с учётом префикса соединения.
     */
    private const TABLE = '{{%book_authors}}';

    /**
     * Имя таблицы книг (для построения внешнего ключа).
     */
    private const BOOKS = '{{%books}}';

    /**
     * Имя таблицы авторов (для построения внешнего ключа).
     */
    private const AUTHORS = '{{%authors}}';

    /**
     * Применяет миграцию: создаёт таблицу `book_authors`, составной PK,
     * индекс по author_id и два внешних ключа с каскадным удалением.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'book_id' => $this->integer()->notNull()->comment('Идентификатор книги'),
            'author_id' => $this->integer()->notNull()->comment('Идентификатор автора'),
        ], $this->tableOptions());

        $this->addCommentOnTable(self::TABLE, 'Связь книг и авторов (M:N)');

        $this->addPrimaryKey('pk-book_authors', self::TABLE, ['book_id', 'author_id']);

        $this->createIndex('idx-book_authors-author_id', self::TABLE, 'author_id');

        $this->addForeignKey(
            'fk-book_authors-book_id',
            self::TABLE,
            'book_id',
            self::BOOKS,
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->addForeignKey(
            'fk-book_authors-author_id',
            self::TABLE,
            'author_id',
            self::AUTHORS,
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    /**
     * Откатывает миграцию: удаляет таблицу `book_authors`
     * (вместе с ней пропадают и внешние ключи, и индексы).
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