<?php

/** @noinspection PhpUnhandledExceptionInspection — исключения из шаблонов обрабатывает yii\web\ErrorHandler */

use app\models\Author;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Book $model */
/** @var yii\widgets\ActiveForm $form */

// Жёсткий лимит на случай разрастания каталога: при тысячах авторов
// listBox становится непригоден, нужен Select2/AJAX-поиск.
$authors = ArrayHelper::map(
    Author::find()
        ->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC])
        ->limit(500)
        ->all(),
    'id',
    'fullName',
);
?>

<div class="book-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'year')->textInput() ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 6]) ?>

    <?= $form->field($model, 'isbn')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'cover_image')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'authorIds')->listBox($authors, [
        'multiple' => true,
        'size' => 8,
    ])->hint('Выберите одного или нескольких авторов (Ctrl/Cmd — для выбора нескольких).') ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>