<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Book;
use app\models\BookSearch;
use app\services\BookService;
use Throwable;
use Yii;
use yii\base\Module;
use yii\db\Exception;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Контроллер CRUD-операций над сущностью «Книга».
 *
 * Просмотр (index/view) доступен всем пользователям, включая гостей.
 * Авторизация на редактирование/удаление будет навешена отдельно
 * через {@see behaviors()} (см. фильтр доступа).
 *
 * @package app\controllers
 *
 * @extends Controller
 *
 * @noinspection PhpUnused — инстанцируется Yii-роутером по имени из URL
 */
class BookController extends Controller
{
    /**
     * @param string $id Идентификатор контроллера.
     * @param Module $module Модуль, в котором живёт контроллер.
     * @param BookService $bookService Сервис записи книг (save + уведомление подписчиков).
     * @param array<string, mixed> $config Доп. конфиг, прокидывается в `Component::__construct`.
     */
    public function __construct(
        $id,
        $module,
        private readonly BookService $bookService,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Возвращает список поведений контроллера.
     *
     * `AccessControl` пускает гостей только на просмотр (`index`, `view`);
     * на создание/редактирование/удаление требуется аутентификация.
     * `VerbFilter` ограничивает удаление методом POST, чтобы случайные
     * GET-запросы (например, от поисковых ботов) не сносили записи.
     *
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => AccessControl::class,
                    'only' => ['create', 'update', 'delete'],
                    'rules' => [
                        [
                            'allow' => true,
                            'actions' => ['create', 'update', 'delete'],
                            'roles' => ['@'],
                        ],
                    ],
                ],
                'verbs' => [
                    'class' => VerbFilter::class,
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ],
        );
    }

    /**
     * Выводит список книг с поиском и пагинацией.
     *
     * @return string HTML страницы со списком.
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionIndex(): string
    {
        $searchModel = new BookSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Отображает карточку одной книги.
     *
     * @param int $id Идентификатор книги.
     *
     * @return string HTML карточки.
     *
     * @throws NotFoundHttpException Если книга с таким id не найдена.
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Создаёт новую книгу. При успешном сохранении делает редирект
     * на страницу просмотра. Уведомления подписчикам и прочая бизнес-обвязка
     * — внутри {@see BookService::create()}.
     *
     * @return Response|string Response при успешном сохранении, HTML формы — иначе.
     *
     * @throws Exception При сбое уровня БД во время сохранения.
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionCreate(): Response|string
    {
        $model = new Book();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $this->bookService->create($model)) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Обновляет данные существующей книги. При успешном сохранении
     * делает редирект на страницу просмотра.
     *
     * @param int $id Идентификатор книги.
     *
     * @return Response|string Response при успешном сохранении, HTML формы — иначе.
     *
     * @throws NotFoundHttpException Если книга с таким id не найдена.
     * @throws Exception При сбое уровня БД во время сохранения.
     * @throws Throwable Прочие непредвиденные сбои.
     *
     * При параллельном редактировании другим пользователем
     * {@see StaleObjectException} перехватывается локально и
     * пользователю показывается flash-сообщение с редиректом на форму.
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post())) {
            try {
                if ($this->bookService->update($model)) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            } catch (StaleObjectException) {
                Yii::$app->session->setFlash(
                    'error',
                    'Эту книгу одновременно редактировал другой пользователь. '
                    . 'Открытая у вас форма устарела — перечитайте актуальные данные и попробуйте снова.',
                );
                return $this->redirect(['update', 'id' => $id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Удаляет книгу и делает редирект на список.
     *
     * Принимает только POST-запросы (см. {@see behaviors()}). Связанные
     * `book_authors` удаляются каскадно на уровне БД.
     *
     * @param int $id Идентификатор книги.
     *
     * @return Response Редирект на index.
     *
     * @throws NotFoundHttpException Если книга с таким id не найдена.
     * @throws StaleObjectException Если запись была изменена в параллельной транзакции (актуально при оптимистичной блокировке).
     * @throws Throwable Прочие сбои уровня БД при удалении.
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionDelete(int $id): Response
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Находит книгу по id или бросает 404.
     *
     * @param int $id Идентификатор книги.
     *
     * @return Book Найденная модель.
     *
     * @throws NotFoundHttpException Если книга с таким id не найдена.
     */
    protected function findModel(int $id): Book
    {
        if (($model = Book::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Запрошенная страница не найдена.');
    }
}
