<?php

/** @noinspection PhpUnhandledExceptionInspection — исключения из шаблонов обрабатывает yii\web\ErrorHandler */

use app\models\Subscription;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\YiiAsset;
use yii\widgets\ActiveForm;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Author $model */

$this->title = $model->fullName;
$this->params['breadcrumbs'][] = ['label' => 'Авторы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
YiiAsset::register($this);
?>
<div class="author-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (!Yii::$app->user->isGuest): ?>
    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Удалить этого автора?',
                'method' => 'post',
            ],
        ]) ?>
    </p>
    <?php endif; ?>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'last_name',
            'first_name',
            'middle_name',
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y H:i'],
            ],
            [
                'attribute' => 'updated_at',
                'format' => ['datetime', 'php:d.m.Y H:i'],
            ],
        ],
    ]) ?>

    <hr id="subscribe">

    <h2>Подписка на новые книги</h2>
    <p class="text-body-secondary">
        Оставьте номер телефона — пришлём SMS, как только у автора появится новая книга.
    </p>

    <?php $subscription = new Subscription(['author_id' => $model->id]); ?>
    <?php $form = ActiveForm::begin([
        'action' => Url::to(['/subscription/create']),
        'fieldConfig' => ['template' => "{label}\n{input}\n{hint}\n{error}"],
    ]); ?>

    <?= Html::activeHiddenInput($subscription, 'author_id') ?>

    <?= $form->field($subscription, 'phone')
        ->textInput(['placeholder' => '+79123456789', 'maxlength' => 20])
        ->hint('Допустимые форматы: +7 (912) 345-67-89, 8 912 345 67 89 — будут приведены к +79123456789.') ?>

    <div class="form-group mt-2">
        <?= Html::submitButton('Подписаться', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
