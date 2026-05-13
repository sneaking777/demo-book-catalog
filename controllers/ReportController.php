<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Author;
use app\models\Book;
use Yii;
use yii\web\Controller;

/**
 * Контроллер отчётов по каталогу.
 *
 * Все отчёты доступны без авторизации — таково требование задачи.
 *
 * @package app\controllers
 *
 * @extends Controller
 *
 * @noinspection PhpUnused — инстанцируется Yii-роутером по имени из URL
 */
class ReportController extends Controller
{
    /**
     * Сколько строк показывать в отчёте по умолчанию.
     */
    private const TOP_LIMIT = 10;

    /**
     * Отчёт: ТОП-{@see TOP_LIMIT} авторов по количеству выпущенных книг
     * за указанный год.
     *
     * Год берётся из GET-параметра `year`. Если параметр не передан или
     * некорректен — подставляется текущий год. Поддерживается диапазон
     * от {@see Book::MIN_YEAR} до текущего года + 1 (на случай, если
     * книга на будущий год уже занесена в каталог).
     *
     * Сортировка: сначала по количеству книг по убыванию, затем по
     * фамилии по возрастанию (стабильный порядок при равных счётчиках).
     *
     * @return string HTML страницы с формой выбора года и таблицей результатов.
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionTopAuthors(): string
    {
        $currentYear = (int) date('Y');
        $maxYear = $currentYear + 1;

        $yearInput = Yii::$app->request->getQueryParam('year');
        $year = $this->normalizeYear($yearInput, $currentYear, $maxYear);

        $rows = Author::find()
            ->alias('a')
            ->select([
                'a.id',
                'a.last_name',
                'a.first_name',
                'a.middle_name',
                'books_count' => 'COUNT(b.id)',
            ])
            ->innerJoin('{{%book_authors}} ba', 'ba.author_id = a.id')
            ->innerJoin('{{%books}} b', 'b.id = ba.book_id')
            ->where(['b.year' => $year])
            ->groupBy(['a.id'])
            ->orderBy(['books_count' => SORT_DESC, 'a.last_name' => SORT_ASC])
            ->limit(self::TOP_LIMIT)
            ->asArray()
            ->all();

        return $this->render('top-authors', [
            'year' => $year,
            'minYear' => Book::MIN_YEAR,
            'maxYear' => $maxYear,
            'rows' => $rows,
            'limit' => self::TOP_LIMIT,
        ]);
    }

    /**
     * Приводит произвольный пользовательский ввод к допустимому году
     * для отчёта. Если ввод некорректен или вне диапазона —
     * возвращает текущий год.
     *
     * @param mixed $input Произвольное значение из GET-параметра.
     * @param int $currentYear Текущий год (фолбэк).
     * @param int $maxYear Верхняя граница допустимого диапазона.
     *
     * @return int Нормализованный год.
     */
    private function normalizeYear(mixed $input, int $currentYear, int $maxYear): int
    {
        if (!is_scalar($input)) {
            return $currentYear;
        }

        $year = (int) $input;
        if ($year < Book::MIN_YEAR || $year > $maxYear) {
            return $currentYear;
        }

        return $year;
    }
}
