<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\widgets\LinkPager;

/** @var yii\web\View $this */
/** @var string $id */
/** @var websvc\audit\models\AuditRequest|null $request */
/** @var yii\data\ActiveDataProvider $changes */

$this->title = Yii::t('audit', 'Request {id}', ['id' => $id]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('audit', 'Requests'), 'url' => ['requests']];
$this->params['breadcrumbs'][] = $this->title;
$jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
?>
<div class="audit-log-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_tabs', ['active' => '']) ?>

    <?php if ($request !== null) { ?>
        <?= DetailView::widget([
            'model' => $request,
            'attributes' => [
                [
                    'attribute' => 'created_at',
                    'value' => Yii::$app->formatter->asDatetime($request->created_at . ' UTC', 'php:Y-m-d H:i:s'),
                ],
                [
                    'attribute' => 'user_name',
                    'value' => trim(($request->user_name ?? '') . ' (' . ($request->user_id ?? '-') . ')'),
                ],
                'app_id',
                'ip',
                'method',
                'route',
                'url',
                'status_code',
                'duration_ms',
                'user_agent',
                [
                    'attribute' => 'params',
                    'format' => 'raw',
                    'value' => Html::tag(
                        'pre',
                        Html::encode((string) json_encode($request->getParamsArray(), $jsonFlags))
                    ),
                ],
            ],
        ]) ?>
    <?php } else { ?>
        <p><?= Html::encode(Yii::t('audit', 'No request row was recorded for this id.')) ?></p>
    <?php } ?>

    <h2><?= Html::encode(Yii::t('audit', 'Changes')) ?></h2>

    <?php foreach ($changes->getModels() as $change) { ?>
        <?= $this->render('_changes', ['change' => $change]) ?>
    <?php } ?>

    <?= LinkPager::widget(['pagination' => $changes->getPagination()]) ?>
</div>
