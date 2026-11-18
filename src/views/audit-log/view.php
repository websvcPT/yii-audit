<?php

use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var yii\web\View $this */
/** @var string $id */
/** @var websvc\audit\models\AuditRequest|null $request */
/** @var yii\data\ActiveDataProvider $changes */

$this->title = Yii::t('audit', 'Request {id}', ['id' => $id]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('audit', 'Requests'), 'url' => ['requests']];
$this->params['breadcrumbs'][] = $this->title;
$total = $changes->getTotalCount();
?>
<div class="audit-log-view">
    <?php if ($this->context->showTitle ?? true) { ?>
        <h1><?= Html::encode($this->title) ?></h1>
    <?php } ?>

    <?= $this->render('_tabs', ['active' => '']) ?>

    <?php if ($request !== null) { ?>
        <?= $this->render('_request_summary', ['request' => $request]) ?>
    <?php } else { ?>
        <p class="text-muted"><?= Html::encode(Yii::t('audit', 'No request row was recorded for this id.')) ?></p>
    <?php } ?>

    <h2><?= Html::encode(Yii::t('audit', 'Changes ({count})', ['count' => (string) $total])) ?></h2>

    <?php if ($total === 0) { ?>
        <p class="text-muted"><?= Html::encode(Yii::t('audit', '(none)')) ?></p>
    <?php } else { ?>
        <table class="table table-sm table-bordered audit-log-changes">
            <thead>
                <tr>
                    <th><?= Html::encode(Yii::t('audit', 'Record')) ?></th>
                    <th><?= Html::encode(Yii::t('audit', 'Action')) ?></th>
                    <th><?= Html::encode(Yii::t('audit', 'Field')) ?></th>
                    <th><?= Html::encode(Yii::t('audit', 'Before')) ?></th>
                    <th><?= Html::encode(Yii::t('audit', 'After')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($changes->getModels() as $change) { ?>
                    <?= $this->render('_change_rows', ['change' => $change]) ?>
                <?php } ?>
            </tbody>
        </table>

        <?= LinkPager::widget(['pagination' => $changes->getPagination()]) ?>
    <?php } ?>
</div>
