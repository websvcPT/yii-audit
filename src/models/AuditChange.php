<?php

namespace websvc\audit\models;

use websvc\audit\Audit;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Connection;

/**
 * One recorded model change.
 *
 * @property int $id
 * @property string $request_id
 * @property string $created_at UTC
 * @property string $app_id
 * @property string|null $user_id
 * @property string|null $user_name
 * @property string $action
 * @property string $model_class
 * @property string $table_name
 * @property string|null $record_id
 * @property array|string $changes
 * @property-read AuditRequest|null $request
 */
class AuditChange extends ActiveRecord
{
    use JsonValueTrait;

    public static function getDb(): Connection
    {
        return Audit::getInstance()->getDb();
    }

    public static function tableName(): string
    {
        return Audit::getInstance()->tableName(Audit::TABLE_CHANGES);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('audit', 'ID'),
            'request_id' => Yii::t('audit', 'Request'),
            'created_at' => Yii::t('audit', 'Time'),
            'app_id' => Yii::t('audit', 'Application'),
            'user_id' => Yii::t('audit', 'User ID'),
            'user_name' => Yii::t('audit', 'User'),
            'action' => Yii::t('audit', 'Action'),
            'model_class' => Yii::t('audit', 'Model'),
            'table_name' => Yii::t('audit', 'Table'),
            'record_id' => Yii::t('audit', 'Record'),
            'changes' => Yii::t('audit', 'Changes'),
        ];
    }

    public function getRequest(): ActiveQuery
    {
        return $this->hasOne(AuditRequest::class, ['request_id' => 'request_id']);
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChangesArray(): array
    {
        return static::decodeJsonValue($this->changes);
    }

    /**
     * @return string[]
     */
    public function getChangedAttributeNames(): array
    {
        return array_keys($this->getChangesArray());
    }
}
