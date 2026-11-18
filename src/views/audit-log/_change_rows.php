<?php

use websvc\audit\Audit;
use websvc\audit\components\ValueFormatter;
use yii\helpers\Html;
use yii\helpers\StringHelper;

/** @var yii\web\View $this */
/** @var websvc\audit\models\AuditChange $change */

$actions = [
    Audit::ACTION_INSERT => Yii::t('audit', 'Insert'),
    Audit::ACTION_UPDATE => Yii::t('audit', 'Update'),
    Audit::ACTION_DELETE => Yii::t('audit', 'Delete'),
];
$values = $change->getChangesArray();
ksort($values);
if ($values === []) {
    return;
}
$rowspan = count($values);
$notSet = Html::tag('span', Html::encode(Yii::t('audit', '(not set)')), ['class' => 'text-muted']);
// An insert has nothing before and a delete nothing after; those cells stay empty instead of "not set"
$showOld = $change->action !== Audit::ACTION_INSERT;
$showNew = $change->action !== Audit::ACTION_DELETE;
$first = true;

foreach ($values as $attribute => $value) {
    $label = $change->getRecordAttributeLabel((string) $attribute);
    $old = ValueFormatter::format($value['old'] ?? null);
    $new = ValueFormatter::format($value['new'] ?? null);
    ?>
    <tr<?= $first ? ' class="audit-log-change"' : '' ?>>
        <?php if ($first) { ?>
            <td rowspan="<?= $rowspan ?>">
                <?= Html::tag('span', Html::encode(StringHelper::basename($change->model_class)), [
                    'title' => $change->model_class,
                ]) ?>
                <?= Html::encode($change->record_id === null ? '' : '#' . $change->record_id) ?>
                <br>
                <small class="text-muted">
                    <?= Html::encode(Yii::$app->formatter->asDatetime($change->created_at . ' UTC', 'php:H:i:s')) ?>
                </small>
            </td>
            <td rowspan="<?= $rowspan ?>"><?= Html::encode($actions[$change->action] ?? $change->action) ?></td>
        <?php } ?>
        <td>
            <?= Html::encode($label) ?>
            <?php if ($label !== $attribute) { ?>
                <small class="text-muted">(<?= Html::encode($attribute) ?>)</small>
            <?php } ?>
        </td>
        <td><?= $showOld ? ($old === null ? $notSet : Html::tag('del', Html::encode($old))) : '' ?></td>
        <td><?= $showNew ? ($new === null ? $notSet : Html::tag('strong', Html::encode($new))) : '' ?></td>
    </tr>
    <?php
    $first = false;
}
