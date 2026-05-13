<?php

declare(strict_types=1);

namespace app\commands;

use app\models\Author;
use app\models\Book;
use app\models\Subscription;
use Faker\Factory;
use Faker\Generator;
use Random\RandomException;
use Throwable;
use Yii;
use yii\base\Exception as BaseException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Exception as DbException;
use yii\helpers\FileHelper;

/**
 * Консольный сидер: заливает в БД случайных авторов и книги с обложками.
 *
 * Полезен для демонстрации каталога проверяющему: после
 * `./bin/yii seed` главная и список книг показываются заполненными,
 * а не пустыми.
 *
 * Все данные генерируются с помощью fakerphp в локали `ru_RU` —
 * ФИО, тексты, ISBN. Обложки скачиваются с picsum.photos.
 *
 * Перед заливкой текущие подписки, книги и авторы **удаляются**,
 * чтобы команда была идемпотентной (повторный запуск не даёт дублей
 * и не разрастает каталог).
 *
 * @package app\commands
 *
 * @extends Controller
 *
 * @noinspection PhpUnused — инстанцируется консольным роутером Yii
 */
class SeedController extends Controller
{
    /**
     * URL источника случайных обложек.
     */
    private const COVER_URL = 'https://picsum.photos/400/600';

    /**
     * Мужские отчества для случайной генерации (Faker для ru_RU
     * `middleName()` нестабилен между версиями, проще иметь короткий
     * локальный пул).
     *
     * @var string[]
     */
    private const PATRONYMICS_MALE = [
        'Александрович', 'Алексеевич', 'Андреевич', 'Борисович', 'Викторович',
        'Владимирович', 'Дмитриевич', 'Иванович', 'Михайлович', 'Николаевич',
        'Петрович', 'Сергеевич', 'Юрьевич', 'Григорьевич', 'Павлович',
    ];

    /**
     * Женские отчества.
     *
     * @var string[]
     */
    private const PATRONYMICS_FEMALE = [
        'Александровна', 'Алексеевна', 'Андреевна', 'Борисовна', 'Викторовна',
        'Владимировна', 'Дмитриевна', 'Ивановна', 'Михайловна', 'Николаевна',
        'Петровна', 'Сергеевна', 'Юрьевна', 'Григорьевна', 'Павловна',
    ];

    /**
     * Опция CLI: сколько авторов создать.
     */
    public int $authorsCount = 20;

    /**
     * Опция CLI: сколько книг создать.
     */
    public int $booksCount = 100;

    /**
     * Опция CLI: не скачивать обложки (быстрее, без сети).
     */
    public bool $skipCovers = false;

    /**
     * Faker для русской локали.
     */
    private Generator $faker;

    /**
     * Инициализирует Faker при создании контроллера.
     *
     * @return void
     */
    public function init(): void
    {
        parent::init();
        $this->faker = Factory::create('ru_RU');
    }

    /**
     * Объявляет именованные опции экшена, чтобы их можно было
     * передавать в виде `--authorsCount=…`, а не позиционно.
     *
     * @param string $actionID Идентификатор экшена.
     *
     * @return string[] Список допустимых имён опций.
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'authorsCount',
            'booksCount',
            'skipCovers',
        ]);
    }

    /**
     * Заливает в БД случайных авторов и книги.
     *
     * Параметры — через одноимённые опции:
     * `./bin/yii seed --authorsCount=20 --booksCount=100 --skipCovers=1`.
     *
     * @return int Код выхода в терминах {@see ExitCode}.
     *
     * @throws Throwable При сбое уровня БД во время очистки/сохранения.
     * @throws BaseException При сбое генератора случайных строк.
     * @throws RandomException При сбое системного генератора случайных чисел.
     * @throws DbException При сбое уровня БД во время вставки связей.
     *
     * @noinspection PhpUnused — вызывается консольным роутером
     */
    public function actionIndex(): int
    {
        $this->truncate();

        $authorIds = $this->seedAuthors($this->authorsCount);
        if ($authorIds === []) {
            $this->stderr("Не создалось ни одного автора, прерываю.\n");
            return ExitCode::DATAERR;
        }

        return $this->seedBooks($this->booksCount, $authorIds, $this->skipCovers);
    }

    /**
     * Удаляет существующие подписки, книги, авторов и файлы обложек.
     *
     * Порядок: подписки → книги (через AR.delete(), чтобы сработал
     * `Book::afterDelete()` и снёс файлы) → авторы. Связки `book_authors`
     * чистятся каскадом на уровне БД.
     *
     * @return void
     *
     * @throws Throwable При сбое уровня БД во время удаления.
     */
    private function truncate(): void
    {
        $this->stdout("Чищу существующие данные...\n");

        Subscription::deleteAll();

        foreach (Book::find()->all() as $book) {
            $book->delete();
        }

        Author::deleteAll();
    }

    /**
     * Создаёт указанное количество случайных авторов.
     *
     * @param int $count Сколько создать.
     *
     * @return int[] Массив id созданных авторов.
     *
     * @throws RandomException При сбое системного генератора случайных чисел.
     * @throws DbException
     */
    private function seedAuthors(int $count): array
    {
        $this->stdout("Создаю $count авторов...\n");
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $isMale = (bool) random_int(0, 1);
            $gender = $isMale ? 'male' : 'female';

            $author = new Author();
            $author->last_name = $this->faker->lastName($gender);
            $author->first_name = $this->faker->firstName($gender);
            $author->middle_name = $isMale
                ? self::PATRONYMICS_MALE[array_rand(self::PATRONYMICS_MALE)]
                : self::PATRONYMICS_FEMALE[array_rand(self::PATRONYMICS_FEMALE)];

            if (!$author->save()) {
                $this->stderr('Ошибка автора: ' . json_encode($author->getErrors()) . "\n");
                continue;
            }

            $ids[] = $author->id;
        }

        return $ids;
    }

    /**
     * Создаёт указанное количество случайных книг, привязывая их
     * к 1-3 случайным авторам из переданного списка id.
     *
     * @param int $count Сколько создать.
     * @param int[] $authorIds Идентификаторы доступных авторов.
     * @param bool $skipCovers Не качать обложки.
     *
     * @return int Код выхода.
     *
     * @throws BaseException При сбое генератора случайных строк (имя файла обложки).
     * @throws RandomException При сбое системного генератора случайных чисел.
     * @throws DbException При сбое уровня БД во время вставки связей `book_authors`.
     */
    private function seedBooks(int $count, array $authorIds, bool $skipCovers): int
    {
        $this->stdout("Создаю $count книг" . ($skipCovers ? '' : ' (с обложками — займёт время)') . "...\n");

        FileHelper::createDirectory(Book::coverDir());

        $currentYear = (int) date('Y');
        $maxAuthorsPerBook = min(3, count($authorIds));

        for ($i = 0; $i < $count; $i++) {
            $book = new Book();
            $book->title = $this->generateTitle();
            $book->year = random_int(1950, $currentYear);
            $book->description = $this->faker->realText(500);
            $book->isbn = $this->faker->unique()->isbn13();
            $book->authorIds = $this->pickRandomAuthors($authorIds, $maxAuthorsPerBook);

            if (!$skipCovers) {
                $filename = $this->downloadCover();
                if ($filename !== null) {
                    $book->cover_image = $filename;
                }
            }

            if (!$book->save()) {
                $this->stderr('Ошибка книги: ' . json_encode($book->getErrors()) . "\n");
                return ExitCode::DATAERR;
            }

            if (($i + 1) % 10 === 0) {
                $this->stdout('  ' . ($i + 1) . " / $count\n");
            }
        }

        $this->stdout("Готово.\n");
        return ExitCode::OK;
    }

    /**
     * Генерирует «правдоподобный» заголовок книги: берёт случайный
     * фрагмент русского текста, обрезает до разумной длины и удаляет
     * висячую пунктуацию. Заглавной делает только первую букву —
     * по правилам русского языка в названии произведения с прописной
     * пишется только первое слово (и имена собственные).
     *
     * @return string
     */
    private function generateTitle(): string
    {
        $raw = $this->faker->realText(60);
        $clean = rtrim(mb_substr($raw, 0, 100), " .,!?:;-—");

        return mb_strtoupper(mb_substr($clean, 0, 1)) . mb_substr($clean, 1);
    }

    /**
     * Выбирает случайных авторов для одной книги: 1..N из переданного
     * пула, без повторов.
     *
     * @param int[] $authorIds Пул доступных id.
     * @param int $maxCount Верхняя граница на размер выборки.
     *
     * @return int[] Идентификаторы выбранных авторов.
     *
     * @throws RandomException При сбое системного генератора случайных чисел.
     */
    private function pickRandomAuthors(array $authorIds, int $maxCount): array
    {
        $pickCount = random_int(1, $maxCount);
        $keys = array_rand($authorIds, $pickCount);

        return is_array($keys)
            ? array_map(static fn (int $k) => $authorIds[$k], $keys)
            : [$authorIds[$keys]];
    }

    /**
     * Скачивает случайную обложку с {@see COVER_URL} и сохраняет
     * в каталог обложек ({@see Book::coverDir()}) со случайным именем.
     *
     * @return string|null Имя файла (без пути) или null, если скачать не удалось.
     *
     * @throws BaseException При сбое генератора случайных строк.
     */
    private function downloadCover(): ?string
    {
        $data = @file_get_contents(self::COVER_URL);
        if ($data === false) {
            return null;
        }

        $filename = Yii::$app->security->generateRandomString(16) . '.jpg';

        if (file_put_contents(Book::coverPath($filename), $data) === false) {
            return null;
        }

        return $filename;
    }
}
