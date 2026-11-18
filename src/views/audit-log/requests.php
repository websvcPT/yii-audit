<?php

use websvc\audit\Audit;
use websvc\audit\models\AuditRequest;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var websvc\audit\models\AuditRequestSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('audit', 'Requests');
$this->params['breadcrumbs'][] = $this->title;
$methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', Audit::METHOD_CLI];
?>
<div class="audit-log-requests">
    <?php if ($this->context->showTitle ?? true) { ?>
        <h1><?= Html::encode($this->title) ?></h1>
    <?php } ?>

    <?= $this->render('_tabs', ['active' => 'requests']) ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'created_at',
                'value' => static fn(AuditRequest $model): string => Yii::$app->formatter->asDatetime(
                    $model->created_at . ' UTC',
                    'php:Y-m-d H:i:s'
                ),
                'filter' => Html::activeInput('date', $searchModel, 'created_from', ['class' => 'form-control'])
                    . Html::activeInput('date', $searchModel, 'created_to', ['class' => 'form-control']),
            ],
            [
                'attribute' => 'user_name',
                'label' => Yii::t('audit', 'User'),
                'value' => static fn(AuditRequest $model): string => (string) ($model->user_name ?? $model->user_id),
                'filter' => Html::activeTextInput($searchModel, 'user', ['class' => 'form-control']),
            ],
            'app_id',
            [
                'attribute' => 'method',
                'filter' => array_combine($methods, $methods),
            ],
            'route',
            'status_code',
            [
                'attribute' => 'duration_ms',
                'filter' => false,
            ],
            'ip',
            [
                'format' => 'raw',
                'value' => static fn(AuditRequest $model): string => Html::a(
                    Html::encode(Yii::t('audit', 'View')),
                    ['view', 'id' => $model->request_id]
                ),
            ],
        ],
    ]) ?>
</div>
