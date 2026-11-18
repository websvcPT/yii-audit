<?php

use websvc\audit\Audit;
use websvc\audit\models\AuditChange;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\StringHelper;

/** @var yii\web\View $this */
/** @var websvc\audit\models\AuditChangeSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('audit', 'Changes');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="audit-log-index">
    <?php if ($this->context->showTitle ?? true) { ?>
        <h1><?= Html::encode($this->title) ?></h1>
    <?php } ?>

    <?= $this->render('_tabs', ['active' => 'index']) ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'created_at',
                'value' => static fn(AuditChange $model): string => Yii::$app->formatter->asDatetime(
                    $model->created_at . ' UTC',
                    'php:Y-m-d H:i:s'
                ),
                'filter' => Html::activeInput('date', $searchModel, 'created_from', ['class' => 'form-control'])
                    . Html::activeInput('date', $searchModel, 'created_to', ['class' => 'form-control']),
            ],
            [
                'attribute' => 'user_name',
                'label' => Yii::t('audit', 'User'),
                'value' => static fn(AuditChange $model): string => (string) ($model->user_name ?? $model->user_id),
                'filter' => Html::activeTextInput($searchModel, 'user', ['class' => 'form-control']),
            ],
            'app_id',
            [
                'attribute' => 'action',
                'filter' => [
                    Audit::ACTION_INSERT => Yii::t('audit', 'Insert'),
                    Audit::ACTION_UPDATE => Yii::t('audit', 'Update'),
                    Audit::ACTION_DELETE => Yii::t('audit', 'Delete'),
                ],
            ],
            [
                'attribute' => 'model_class',
                'format' => 'raw',
                'value' => static fn(AuditChange $model): string => Html::tag(
                    'span',
                    Html::encode(StringHelper::basename($model->model_class)),
                    ['title' => $model->model_class]
                ),
            ],
            'record_id',
            [
                'label' => Yii::t('audit', 'Changed attributes'),
                'value' => static fn(AuditChange $model): string => implode(
                    ', ',
                    $model->getChangedAttributeNames()
                ),
                'filter' => Html::activeTextInput($searchModel, 'field', ['class' => 'form-control']),
            ],
            [
                'label' => Yii::t('audit', 'Request'),
                'format' => 'raw',
                'value' => static fn(AuditChange $model): string => Html::a(
                    Html::encode(Yii::t('audit', 'View')),
                    ['view', 'id' => $model->request_id]
                ),
                'filter' => Html::activeTextInput($searchModel, 'request_id', ['class' => 'form-control']),
            ],
        ],
    ]) ?>
</div>
