<?php

namespace websvc\audit\tests\data\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string|null $text
 */
class Note extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'notes';
    }
}
