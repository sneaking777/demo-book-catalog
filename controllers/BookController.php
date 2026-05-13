<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Book;
use app\models\BookSearch;
use Throwable;
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
     * на страницу просмотра.
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
            if ($model->load($this->request->post()) && $model->save()) {
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
     *
     * @noinspection PhpUnused — вызывается Yii-роутером по имени экшена
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
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