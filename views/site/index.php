<?php

/** @var yii\web\View $this */
/** @var array<int, array{icon: string, title: string, text: string, url: string, cta: string}> $cards */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Каталог книг';
$this->params['meta_description'] = 'Каталог книг и авторов. Подписывайтесь на новинки любимых авторов и получайте уведомления.';
$this->params['meta_keywords'] = 'книги, авторы, каталог, библиотека, подписки';
?>
<div class="site-index">

    <div class="hero-banner text-white rounded-4 p-5 mb-4 position-relative overflow-hidden">
        <div class="position-relative">
            <h1 class="display-5 fw-bold mb-3">Каталог книг и авторов</h1>
            <p class="lead opacity-75 mb-4 hero-lead">
                Удобная картотека: собирайте книги, ведите карточки авторов
                и подписывайте читателей на новинки по SMS.
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <?= Html::a(
                    'Смотреть книги',
                    Url::to(['/book/index']),
                    ['class' => 'btn btn-light btn-lg fw-semibold px-4'],
                ) ?>
                <?= Html::a(
                    'Авторы',
                    Url::to(['/author/index']),
                    ['class' => 'btn btn-outline-light btn-lg px-4'],
                ) ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($cards as $card): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 extension-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="extension-icon" aria-hidden="true"><?= $card['icon'] ?></span>
                            <h3 class="h6 fw-bold mb-0 ms-2"><?= $card['title'] ?></h3>
                        </div>
                        <p class="text-body-secondary small mb-0"><?= $card['text'] ?></p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <?= Html::a(
                            $card['cta'] . ' &raquo;',
                            $card['url'],
                            ['class' => 'btn btn-sm btn-outline-secondary'],
                        ) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
