<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'О проекте';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = 'О каталоге книг: возможности и устройство приложения.';
?>
<div class="site-about d-flex align-items-center justify-content-center text-center">
    <div class="site-about-content mx-auto">
        <h1 class="display-6 fw-semibold mb-3">О проекте</h1>

        <p class="text-body-secondary mb-4">
            Это демо-каталог книг и авторов. Карточки с обложками, мультивыбор авторов,
            подписки гостей на новинки и SMS-уведомления через smspilot.ru.
        </p>

        <?= Html::a(
            'На главную',
            Yii::$app->homeUrl,
            ['class' => 'btn btn-outline-primary btn-lg'],
        ) ?>
    </div>
</div>
