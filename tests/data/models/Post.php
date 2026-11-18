<?php

namespace websvc\audit\tests\data\models;

use websvc\audit\behaviors\AuditBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property int $status
 * @property string|null $price
 * @property string|null $password_hash
 * @property int|null $updated_at
 */
class Post extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'posts';
    }

    public function behaviors(): array
    {
        return [
            'audit' => [
                'class' => AuditBehavior::class,
                'ignoreAttributes' => ['updated_at'],
            ],
        ];
    }
}
