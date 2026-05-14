<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\ContactForm;
use app\models\LoginForm;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\base\Security;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\mail\MailerInterface;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

/**
 * Контроллер «общесайтовых» страниц: главная, статика, аутентификация
 * и форма обратной связи.
 *
 * Здесь же сидит обработчик ошибок ({@see actions()}) и капча для
 * формы контактов. CRUD книг и авторов вынесены в отдельные контроллеры.
 *
 * URL-алиасы для основных экшенов настроены в `config/web.php`
 * (`urlManager.rules`), а 301-редирект со старых `/site/<action>`
 * на канонические короткие URL делает {@see beforeAction()}.
 *
 * @package app\controllers
 *
 * @noinspection PhpUnused — инстанцируется маршрутизатором Yii
 */
class SiteController extends Controller
{
    /**
     * Экшены, для которых в `urlManager.rules` заведён короткий алиас
     * (`/login`, `/about`, `/contact`, `/`). Хиты по `/site/<action>`
     * 301-редиректятся на канонический URL в {@see beforeAction()}.
     *
     * `logout` сюда не попадает: это POST-only, а 301 на POST браузеры
     * обрабатывают непредсказуемо.
     *
     * При изменении синхронизировать с `config/web.php` → `urlManager.rules`.
     *
     * @var string[]
     */
    private const ALIASED_ACTIONS = ['index', 'login', 'about', 'contact'];

    /**
     * Подтягивает зависимости через DI-контейнер Yii.
     *
     * @param string $id Идентификатор контроллера.
     * @param Module $module Модуль, в котором живёт контроллер.
     * @param MailerInterface $mailer Мейлер для отправки писем из формы обратной связи.
     * @param Security $security Сервис проверки паролей, используется в {@see LoginForm}.
     * @param array<string, mixed> $config Доп. конфиг, прокидывается в `Component::__construct`.
     */
    public function __construct(
        $id,
        $module,
        private readonly MailerInterface $mailer,
        private readonly Security $security,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Хук жизненного цикла: до запуска экшена выкидывает 301-редирект
     * со старого «дефолтного» URL `/site/<action>` на короткий алиас
     * (`/login`, `/about`, ...). Сделано, чтобы у каждой страницы был
     * один канонический URL — для SEO и единообразных ссылок.
     *
     * Query-string сохраняется (например, `/site/login?back=foo`
     * → `/login?back=foo`). Список редиректируемых экшенов —
     * в {@see ALIASED_ACTIONS}.
     *
     * @param Action $action Экшен, который Yii собирается выполнить.
     *
     * @return bool `true` — продолжить выполнение экшена;
     *              `false` — пропустить (например, после редиректа).
     *
     * @throws BadRequestHttpException Если родительский фильтр (например, CSRF) отверг запрос.
     * @throws InvalidConfigException Если не удаётся определить pathInfo запроса.
     *
     * @noinspection PhpUnhandledExceptionInspection — исключения уровня контроллера обрабатывает yii\web\ErrorHandler
     * @noinspection PhpDocMissingThrowsInspection — IDE ложно требует throws для веток, которых тут нет
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (in_array($action->id, self::ALIASED_ACTIONS, true)
            && $this->request->getPathInfo() === 'site/' . $action->id
        ) {
            $target = $action->id === 'index' ? '/' : '/' . $action->id;
            $qs = $this->request->getQueryString();
            if ($qs !== '') {
                $target .= '?' . $qs;
            }
            Yii::$app->response->redirect($target, 301);
            return false;
        }

        return true;
    }

    /**
     * Подключает фильтры контроллера:
     * - `access` — пускает на `logout` только аутентифицированных пользователей;
     * - `verbs`  — разрешает `logout` только методом POST (защита от CSRF
     *              через `<a href>`).
     *
     * @return array<string, array<string, mixed>>
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Регистрирует «внешние» экшены, которые поставляются классами:
     * - `error`   — рендер кастомной страницы ошибки ({@see views/site/error.php});
     * - `captcha` — генерация изображения капчи для формы обратной связи.
     *
     * В тестовом окружении капча возвращает фиксированный код `testme`,
     * чтобы прогон функциональных тестов не упирался в случайный код.
     *
     * @return array<string, array<string, mixed>>
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'transparent' => true,
            ],
        ];
    }

    /**
     * Главная страница: hero-баннер с краткой подачей каталога и три
     * карточки-перехода (Книги, Авторы, Топ-10). Данные карточек
     * собираются здесь, чтобы вьюха оставалась чисто шаблонной.
     *
     * @return string Отрендеренный HTML.
     *
     * @noinspection PhpUnused — экшен вызывается маршрутизатором Yii
     */
    public function actionIndex(): string
    {
        $cards = [
            [
                'icon' => '&#128218;',
                'title' => 'Книги',
                'text' => 'Полный список изданий с обложками, годом выпуска и авторами. Поиск, сортировка, мультивыбор авторов в форме.',
                'url' => Url::to(['/book/index']),
                'cta' => 'К книгам',
            ],
            [
                'icon' => '&#9997;',
                'title' => 'Авторы',
                'text' => 'Карточки авторов с биографией и библиографией. Подписывайтесь на новинки конкретного автора без регистрации.',
                'url' => Url::to(['/author/index']),
                'cta' => 'К авторам',
            ],
            [
                'icon' => '&#128202;',
                'title' => 'Топ-10 авторов',
                'text' => 'Готовый отчёт: авторы, у которых больше всего книг в каталоге за выбранный период.',
                'url' => Url::to(['/report/top-authors']),
                'cta' => 'Открыть отчёт',
            ],
        ];

        return $this->render('index', ['cards' => $cards]);
    }

    /**
     * Логин: показывает форму, валидирует учётные данные и при успехе
     * возвращает пользователя на страницу, с которой он пришёл
     * ({@see Controller::goBack()}). Уже залогиненных молча редиректит
     * на главную.
     *
     * Поле пароля затирается перед повторным рендером формы, чтобы
     * не светиться в HTML.
     *
     * @return Response|string Редирект при успехе/уже-логине или HTML формы.
     *
     * @noinspection PhpUnused — экшен вызывается маршрутизатором Yii
     */
    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm($this->security);

        if ($model->load($this->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    /**
     * Логаут: разрывает сессию пользователя и уводит на главную.
     *
     * Принимает только POST (см. {@see behaviors()}), чтобы нельзя было
     * выкинуть пользователя из системы кросс-сайтовой ссылкой `<img>`/`<a>`.
     *
     * @return Response Редирект на главную.
     *
     * @noinspection PhpUnused — экшен вызывается маршрутизатором Yii
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Форма обратной связи: при успешной отправке кладёт письмо
     * через {@see MailerInterface} (в dev — в файл, см. конфиг
     * `mailer.useFileTransport`), показывает flash-сообщение и
     * перезагружает страницу через POST/Redirect/GET.
     *
     * @return Response|string Редирект (`refresh`) при успехе или HTML формы.
     *
     * @noinspection PhpUnused — экшен вызывается маршрутизатором Yii
     */
    public function actionContact(): Response|string
    {
        $model = new ContactForm();

        $contact = $model->load($this->request->post()) && $model->contact(
            $this->mailer,
            Yii::$app->params['adminEmail'],
            Yii::$app->params['senderEmail'],
            Yii::$app->params['senderName'],
        );

        if ($contact) {
            Yii::$app->session->setFlash(
                'success',
                'Спасибо за обращение! Мы ответим вам в ближайшее время.',
            );

            return $this->refresh();
        }

        return $this->render('contact', ['model' => $model]);
    }

    /**
     * Статическая страница «О проекте». Логики никакой — только рендер.
     *
     * @return string Отрендеренный HTML.
     *
     * @noinspection PhpUnused — экшен вызывается маршрутизатором Yii
     */
    public function actionAbout(): string
    {
        return $this->render('about');
    }
}
