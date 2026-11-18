<?php

use websvc\audit\components\ValueFormatter;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var websvc\audit\models\AuditRequest $request */

if ($request->user_name !== null && $request->user_name !== '') {
    $user = $request->user_name . ($request->user_id !== null ? ' (#' . $request->user_id . ')' : '');
} elseif ($request->user_id !== null) {
    $user = '#' . $request->user_id;
} else {
    $user = Yii::t('audit', 'System (no user)');
}

$outcome = $request->status_code === null
    ? Yii::t('audit', 'status unknown')
    : Yii::t('audit', 'status {code}', ['code' => (string) $request->status_code]);
if ($request->duration_ms !== null) {
    $outcome .= ' · ' . $request->duration_ms . ' ms';
}

$details = [
    $user,
    $request->app_id,
    Yii::$app->formatter->asDatetime($request->created_at . ' UTC', 'php:Y-m-d H:i:s'),
];
if ($request->ip !== null && $request->ip !== '') {
    $details[] = $request->ip;
}

$params = ValueFormatter::flatten($request->getParamsArray());
?>
<div class="audit-log-summary">
    <p class="lead">
        <strong><?= Html::encode($request->method) ?></strong>
        <?= Html::encode($request->route) ?>
        <span class="text-muted">→ <?= Html::encode($outcome) ?></span>
    </p>
    <p><?= Html::encode(implode(' · ', $details)) ?></p>
    <?php if ($request->url !== '' && $request->url !== '/' . $request->route) { ?>
        <p class="text-muted"><?= Html::encode($request->url) ?></p>
    <?php } ?>
    <?php if ($request->user_agent !== null && $request->user_agent !== '') { ?>
        <p class="text-muted"><small><?= Html::encode($request->user_agent) ?></small></p>
    <?php } ?>

    <h2><?= Html::encode(Yii::t('audit', 'Parameters')) ?></h2>
    <?php if ($params === []) { ?>
        <p class="text-muted"><?= Html::encode(Yii::t('audit', '(none)')) ?></p>
    <?php } else { ?>
        <table class="table table-sm w-auto audit-log-params">
            <tbody>
                <?php foreach ($params as $name => $value) { ?>
                    <tr>
                        <td><code><?= Html::encode($name) ?></code></td>
                        <td><?= Html::encode($value ?? '') ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
