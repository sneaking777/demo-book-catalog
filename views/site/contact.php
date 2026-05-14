<?php

/** @noinspection PhpUnhandledExceptionInspection — исключения из шаблонов обрабатывает yii\web\ErrorHandler */

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\models\ContactForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\captcha\Captcha;

$this->title = 'Связаться с нами';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = 'Свяжитесь с нами через форму обратной связи каталога книг.';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
?>
<?php if (Yii::$app->session->hasFlash('success')): ?>

<div class="site-contact-success d-flex align-items-center justify-content-center text-center">
    <div class="site-contact-success-content mx-auto">
        <h1 class="display-6 fw-semibold mb-3">Сообщение отправлено</h1>

        <?php if (YII_DEBUG && Yii::$app->mailer->useFileTransport): ?>
            <p class="text-body-tertiary small mb-4">
                Режим разработки: письмо сохранено в
                <code><?= Yii::getAlias(Yii::$app->mailer->fileTransportPath) ?></code>
            </p>
        <?php endif; ?>

        <?= Html::a(
            'Отправить ещё одно',
            ['contact'],
            ['class' => 'btn btn-outline-primary btn-lg'],
        ) ?>
    </div>
</div>

<?php else: ?>

<div class="site-contact d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card login-split-card-wide">
        <div class="row g-0">

            <div class="col-md-4 d-none d-md-flex login-brand-panel text-white">
                <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                    <div class="brand-mark fw-bold">
                        <span class="brand-mark-icon" aria-hidden="true">&#128218;</span>
                        <span class="ms-2"><?= Html::encode(Yii::$app->name) ?></span>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-3 login-brand-title">
                            На связи
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            Есть вопрос, замечание или предложение по каталогу? Напишите — ответим.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="d-md-none mb-3 brand-mark fw-bold fs-5">
                            <span aria-hidden="true">&#128218;</span>
                            <span class="ms-2"><?= Html::encode(Yii::$app->name) ?></span>
                        </div>
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">Заполните форму, и мы свяжемся с вами</p>
                    </div>

                    <?php $form = ActiveForm::begin(['id' => 'contact-form']); ?>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <?= $form->field($model, 'name', [
                                'options' => ['class' => 'mb-0'],
                                'template' => sprintf($htmlIcon, '&#128100;'),
                                'inputOptions' => [
                                    'class' => 'form-control',
                                    'placeholder' => 'Имя',
                                    'autofocus' => true,
                                ],
                            ])->label('Ваше имя', $labelOptions) ?>
                        </div>

                        <div class="col-sm-6 mb-3">
                            <?= $form->field($model, 'email', [
                                'options' => ['class' => 'mb-0'],
                                'template' => sprintf($htmlIcon, '&#9993;'),
                                'inputOptions' => [
                                    'class' => 'form-control',
                                    'placeholder' => 'email@example.com',
                                ],
                            ])->label('E-mail', $labelOptions) ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'subject', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128172;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'Тема',
                            ],
                        ])->label('Тема', $labelOptions) ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'body', [
                            'options' => ['class' => 'mb-0'],
                            'template' => '{label}{input}{error}{hint}',
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'Ваше сообщение...',
                            ],
                        ])->textarea()->label('Сообщение', $labelOptions) ?>
                    </div>

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <?= $form->field($model, 'verifyCode', [
                            'enableLabel' => false,
                            'options' => ['class' => ''],
                            'inputOptions' => ['aria-label' => 'Код проверки'],
                        ])->widget(Captcha::class, [
                            'template' => '<div class="d-flex align-items-center gap-2">{image}{input}</div>',
                        ]) ?>

                        <?= Html::submitButton(
                            'Отправить',
                            [
                                'class' => 'btn login-btn text-white px-4 ms-auto',
                                'name' => 'contact-button',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php endif; ?>
