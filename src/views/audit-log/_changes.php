<?php

use yii\helpers\Html;
use yii\helpers\StringHelper;

/** @var yii\web\View $this */
/** @var websvc\audit\models\AuditChange $change */

$format = static function (mixed $value): string {
    if ($value === null) {
        return Yii::t('audit', '(not set)');
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_array($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return (string) $value;
};
$actions = [
    'insert' => Yii::t('audit', 'Insert'),
    'update' => Yii::t('audit', 'Update'),
    'delete' => Yii::t('audit', 'Delete'),
];
$values = $change->getChangesArray();
ksort($values);
?>
<div class="audit-log-change">
    <h3>
        <?= Html::encode($actions[$change->action] ?? $change->action) ?>
        <?= Html::tag('span', Html::encode(StringHelper::basename($change->model_class)), [
            'title' => $change->model_class,
        ]) ?>
        <?= Html::encode($change->record_id === null ? '' : '#' . $change->record_id) ?>
        <small><?= Html::encode(Yii::$app->formatter->asDatetime($change->created_at, 'php:Y-m-d H:i:s')) ?></small>
    </h3>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th><?= Html::encode(Yii::t('audit', 'Attribute')) ?></th>
                <th><?= Html::encode(Yii::t('audit', 'Old value')) ?></th>
                <th><?= Html::encode(Yii::t('audit', 'New value')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($values as $attribute => $value) { ?>
                <tr>
                    <td><?= Html::encode($attribute) ?></td>
                    <td><?= Html::encode($format($value['old'] ?? null)) ?></td>
                    <td><?= Html::encode($format($value['new'] ?? null)) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
