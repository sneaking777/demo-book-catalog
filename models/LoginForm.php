<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;
use yii\base\Security;

/**
 * Модель формы входа в систему.
 *
 * Не привязана к таблице БД (наследник {@see Model}, а не ActiveRecord).
 * Принимает логин и пароль, валидирует пару через {@see validatePassword()},
 * при успехе авторизует пользователя в компоненте `user`.
 *
 * @property-read User|null $user Пользователь, найденный по введённому логину.
 *
 * @package app\models
 *
 * @extends Model
 */
class LoginForm extends Model
{
    /**
     * Введённый логин.
     */
    public string $username = '';

    /**
     * Введённый пароль (в открытом виде, не сохраняется).
     */
    public string $password = '';

    /**
     * Запомнить пользователя на 30 дней.
     */
    public bool $rememberMe = true;

    /**
     * Найденный пользователь (кэш для {@see getUser()}).
     */
    private User|null $_user = null;

    /**
     * Флаг того, что поиск пользователя уже выполнялся
     * (чтобы не дёргать БД повторно при `$_user === null`).
     */
    private bool $_userLoaded = false;

    /**
     * @param Security $security Компонент Yii для проверки хэша пароля.
     * @param array<string, mixed> $config Конфигурация модели (передаётся в Model::__construct).
     */
    public function __construct(private readonly Security $security, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Возвращает правила валидации атрибутов формы.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Возвращает человекочитаемые названия атрибутов формы.
     * Используются ядром Yii в сообщениях валидации
     * (например, «Необходимо заполнить «Логин»»).
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'username' => 'Логин',
            'password' => 'Пароль',
            'rememberMe' => 'Запомнить меня',
        ];
    }

    /**
     * Инлайн-валидатор для пароля: проверяет, что пара логин/пароль
     * соответствует существующему пользователю.
     *
     * @param string $attribute Имя проверяемого атрибута.
     * @param array<string, mixed>|null $params Дополнительные параметры правила (не используются, но обязательны по контракту валидатора Yii).
     *
     * @return void
     *
     * @noinspection PhpUnused — вызывается Yii-валидатором по имени из rules()
     * @noinspection PhpUnusedParameterInspection — параметр обязателен по контракту inline-валидатора
     */
    public function validatePassword(string $attribute, array|null $params): void
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$this->security->validatePassword($this->password, $user->passwordHash)) {
                $this->addError($attribute, 'Неверный логин или пароль.');
            }
        }
    }

    /**
     * Выполняет вход в систему по введённым логину и паролю.
     *
     * @return bool true, если вход выполнен успешно.
     */
    public function login(): bool
    {
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 30 : 0);
        }

        return false;
    }

    /**
     * Находит пользователя по введённому логину. Результат кэшируется
     * в свойстве, чтобы повторные вызовы не дёргали БД.
     *
     * @return User|null Найденный пользователь или null, если такого нет.
     *
     * @noinspection PhpUnused — вызывается магически через Model::__get() как $loginForm->user
     */
    public function getUser(): User|null
    {
        if (!$this->_userLoaded) {
            $this->_user = User::findByUsername($this->username);
            $this->_userLoaded = true;
        }

        return $this->_user;
    }
}