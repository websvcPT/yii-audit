<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');
defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', false);

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

Yii::setAlias('@bower', $root . '/vendor/bower-asset');
Yii::setAlias('@npm', $root . '/vendor/npm-asset');
