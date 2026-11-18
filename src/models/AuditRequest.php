<?php

namespace websvc\audit\models;

use websvc\audit\Audit;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Connection;

/**
 * One recorded request.
 *
 * @property int $id
 * @property string $request_id
 * @property string $created_at UTC
 * @property string $app_id
 * @property string|null $user_id
 * @property string|null $user_name
 * @property string|null $ip
 * @property string $method
 * @property string $route
 * @property string $url
 * @property array|string|null $params
 * @property int|null $status_code
 * @property int|null $duration_ms
 * @property string|null $user_agent
 * @property-read AuditChange[] $changes
 */
class AuditRequest extends ActiveRecord
{
    use JsonValueTrait;

    public static function getDb(): Connection
    {
        return Audit::getInstance()->getDb();
    }

    public static function tableName(): string
    {
        return Audit::getInstance()->tableName(Audit::TABLE_REQUESTS);
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
            'ip' => Yii::t('audit', 'IP'),
            'method' => Yii::t('audit', 'Method'),
            'route' => Yii::t('audit', 'Route'),
            'url' => Yii::t('audit', 'URL'),
            'params' => Yii::t('audit', 'Parameters'),
            'status_code' => Yii::t('audit', 'Status'),
            'duration_ms' => Yii::t('audit', 'Duration (ms)'),
            'user_agent' => Yii::t('audit', 'User agent'),
        ];
    }

    public function getChanges(): ActiveQuery
    {
        return $this->hasMany(AuditChange::class, ['request_id' => 'request_id'])->orderBy(['id' => SORT_ASC]);
    }

    public function getParamsArray(): array
    {
        return static::decodeJsonValue($this->params);
    }
}
