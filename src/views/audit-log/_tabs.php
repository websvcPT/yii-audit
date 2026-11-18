<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $active */

?>
<p class="audit-log-tabs">
    <?= Html::a(Yii::t('audit', 'Changes'), ['index'], [
        'class' => 'btn ' . ($active === 'index' ? 'btn-primary' : 'btn-default btn-secondary'),
    ]) ?>
    <?= Html::a(Yii::t('audit', 'Requests'), ['requests'], [
        'class' => 'btn ' . ($active === 'requests' ? 'btn-primary' : 'btn-default btn-secondary'),
    ]) ?>
</p>
