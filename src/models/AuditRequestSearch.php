<?php

namespace websvc\audit\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model behind the requests grid.
 */
class AuditRequestSearch extends AuditRequest
{
    use SearchFiltersTrait;

    public function rules(): array
    {
        return [
            [['created_from', 'created_to'], 'date', 'format' => 'php:Y-m-d'],
            [['user', 'app_id', 'method', 'route', 'ip'], 'string', 'max' => 255],
            ['status_code', 'integer'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), $this->filterLabels());
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = AuditRequest::find();
        $provider = $this->createProvider(
            $query,
            ['id', 'created_at', 'app_id', 'user_name', 'method', 'route', 'status_code', 'duration_ms']
        );

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0 = 1');

            return $provider;
        }

        $query->andFilterWhere([
            'app_id' => $this->app_id,
            'method' => is_string($this->method) ? strtoupper($this->method) : $this->method,
            'status_code' => $this->status_code,
            'ip' => $this->ip,
        ]);
        $query->andFilterWhere(['like', 'route', $this->route]);
        $this->applyUserFilter($query);
        $this->applyDateRange($query);

        return $provider;
    }
}
