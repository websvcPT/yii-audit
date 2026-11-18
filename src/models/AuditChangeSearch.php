<?php

namespace websvc\audit\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * Search model behind the changes grid.
 */
class AuditChangeSearch extends AuditChange
{
    use SearchFiltersTrait;

    /**
     * Name of a changed attribute.
     */
    public mixed $field = null;

    public function rules(): array
    {
        return [
            [['created_from', 'created_to'], 'date', 'format' => 'php:Y-m-d'],
            [['user', 'app_id', 'action', 'model_class', 'record_id', 'request_id'], 'string', 'max' => 255],
            ['field', 'match', 'pattern' => '/^[A-Za-z0-9_-]{1,64}$/'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), $this->filterLabels(), [
            'field' => Yii::t('audit', 'Attribute'),
        ]);
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = AuditChange::find();
        $provider = $this->createProvider(
            $query,
            ['id', 'created_at', 'app_id', 'user_name', 'action', 'model_class', 'record_id']
        );

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0 = 1');

            return $provider;
        }

        $query->andFilterWhere([
            'app_id' => $this->app_id,
            'action' => $this->action,
            'record_id' => $this->record_id,
            'request_id' => $this->request_id,
        ]);
        $query->andFilterWhere(['like', 'model_class', $this->model_class]);
        $this->applyUserFilter($query);
        $this->applyDateRange($query);

        if ($this->field !== null && $this->field !== '') {
            $query->andWhere(new Expression(
                'JSON_CONTAINS_PATH(changes, :one, :path)',
                [':one' => 'one', ':path' => '$."' . $this->field . '"']
            ));
        }

        return $provider;
    }
}
