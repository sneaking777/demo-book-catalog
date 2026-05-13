<?php

/** @noinspection PhpUnhandledExceptionInspection — исключения из шаблонов обрабатывает yii\web\ErrorHandler */

use app\models\Book;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\BookSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Книги';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="book-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (!Yii::$app->user->isGuest): ?>
    <p>
        <?= Html::a('Добавить книгу', ['create'], ['class' => 'btn btn-success']) ?>
    </p>
    <?php endif; ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'label' => 'Обложка',
                'format' => 'raw',
                'filter' => false,
                'value' => static fn (Book $book) => $book->coverUrl !== null
                    ? Html::img($book->coverUrl, [
                        'alt' => $book->title,
                        'style' => 'max-height: 60px;',
                    ])
                    : '',
            ],
            [
                'attribute' => 'title',
                'format' => 'raw',
                'value' => static fn (Book $book) => Html::a(
                    Html::encode($book->title),
                    ['view', 'id' => $book->id],
                ),
            ],
            'year',
            [
                'label' => 'Авторы',
                'format' => 'raw',
                'value' => static fn (Book $book) => Html::encode(implode(', ', array_map(
                    static fn ($a) => $a->fullName,
                    $book->authors,
                ))),
            ],
            'isbn',
            [
                'class' => ActionColumn::class,
                'header' => 'Действия',
                'template' => '{update} {delete}',
                'visible' => !Yii::$app->user->isGuest,
                'urlCreator' => function ($action, Book $model) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                },
            ],
        ],
    ]); ?>

</div>
