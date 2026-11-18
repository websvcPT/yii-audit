<?php

namespace websvc\audit\tests\data\models;

use yii\db\ActiveRecord;

/**
 * @property int $post_id
 * @property int $tag_id
 */
class PostTag extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'post_tags';
    }
}
