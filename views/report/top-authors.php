<?php

/** @noinspection PhpUnhandledExceptionInspection — исключения из шаблонов обрабатывает yii\web\ErrorHandler */

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var int $year */
/** @var int $minYear */
/** @var int $maxYear */
/** @var array<int, array<string, mixed>> $rows */
/** @var int $limit */

$this->title = "ТОП-$limit авторов за $year год";
$this->params['breadcrumbs'][] = ['label' => 'Отчёты', 'url' => ['top-authors']];
$this->params['breadcrumbs'][] = $this->title;

$buildFullName = static fn (array $r): string => trim(
    ($r['last_name'] ?? '')
    . ' ' . ($r['first_name'] ?? '')
    . ' ' . ($r['middle_name'] ?? ''),
);
?>
<div class="report-top-authors">

    <h1><?= Html::encode($this->title) ?></h1>

    <form method="get" action="<?= Url::to(['top-authors']) ?>" class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label for="year" class="form-label">Год</label>
            <input
                type="number"
                id="year"
                name="year"
                value="<?= Html::encode((string) $year) ?>"
                min="<?= $minYear ?>"
                max="<?= $maxYear ?>"
                class="form-control"
                required
            >
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Показать</button>
        </div>
    </form>

    <?php if ($rows === []): ?>
        <p class="text-body-secondary">За <?= $year ?> год в каталоге нет книг.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th style="width: 60px;">№</th>
                    <th>Автор</th>
                    <th style="width: 140px;">Книг за год</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?= Html::a(
                                Html::encode($buildFullName($row)),
                                ['/author/view', 'id' => $row['id']],
                            ) ?>
                        </td>
                        <td><?= (int) $row['books_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
