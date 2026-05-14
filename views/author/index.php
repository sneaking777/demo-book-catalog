<?php

/** @noinspection PhpUnhandledExceptionInspection — исключения из шаблонов обрабатывает yii\web\ErrorHandler */

use app\models\Author;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\AuthorSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Авторы';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="author-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (!Yii::$app->user->isGuest): ?>
    <p>
        <?= Html::a('Добавить автора', ['create'], ['class' => 'btn btn-success']) ?>
    </p>
    <?php endif; ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'summary' => '<div class="text-body-secondary small mb-2">Показано <b>{begin}–{end}</b> из <b>{totalCount}</b></div>',
        'pager' => [
            'class' => LinkPager::class,
            'options' => ['class' => 'd-flex justify-content-center mt-3'],
            'maxButtonCount' => 7,
            'firstPageLabel' => '«',
            'lastPageLabel' => '»',
        ],
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'attribute' => 'last_name',
                'format' => 'raw',
                'value' => static fn (Author $author) => Html::a(
                    Html::encode($author->last_name),
                    ['view', 'id' => $author->id],
                ),
            ],
            'first_name',
            'middle_name',
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y H:i'],
            ],
            [
                'label' => 'Подписка',
                'format' => 'raw',
                'value' => static fn (Author $author) => Html::a(
                    'Подписаться',
                    ['view', 'id' => $author->id, '#' => 'subscribe'],
                    ['class' => 'btn btn-sm btn-outline-primary'],
                ),
            ],
            [
                'class' => ActionColumn::class,
                'header' => 'Действия',
                'template' => '{update} {delete}',
                'visible' => !Yii::$app->user->isGuest,
                'urlCreator' => function ($action, Author $model) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                },
            ],
        ],
    ]); ?>

</div>