<?php

namespace websvc\audit\models;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Filters shared by the search models.
 */
trait SearchFiltersTrait
{
    /**
     * Start date (`Y-m-d`, application time zone), inclusive.
     */
    public mixed $created_from = null;

    /**
     * End date (`Y-m-d`, application time zone), inclusive.
     */
    public mixed $created_to = null;

    /**
     * User id (exact) or part of the user name.
     */
    public mixed $user = null;

    protected function filterLabels(): array
    {
        return [
            'created_from' => Yii::t('audit', 'From'),
            'created_to' => Yii::t('audit', 'To'),
            'user' => Yii::t('audit', 'User'),
        ];
    }

    /**
     * @param string[] $sortAttributes
     */
    protected function createProvider(ActiveQuery $query, array $sortAttributes): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => $sortAttributes,
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => ['pageSize' => 50],
        ]);
    }

    protected function applyUserFilter(ActiveQuery $query): void
    {
        if ($this->user === null || $this->user === '') {
            return;
        }
        $query->andWhere(['or', ['user_id' => $this->user], ['like', 'user_name', $this->user]]);
    }

    /**
     * Converts the dates, entered in the application time zone, to the UTC range stored in `created_at`.
     */
    protected function applyDateRange(ActiveQuery $query): void
    {
        $timeZone = new DateTimeZone(Yii::$app->timeZone);
        $utc = new DateTimeZone('UTC');

        if ($this->created_from !== null && $this->created_from !== '') {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $this->created_from, $timeZone);
            $query->andWhere(['>=', 'created_at', $start->setTimezone($utc)->format('Y-m-d H:i:s')]);
        }
        if ($this->created_to !== null && $this->created_to !== '') {
            $end = DateTimeImmutable::createFromFormat('!Y-m-d', $this->created_to, $timeZone)->modify('+1 day');
            $query->andWhere(['<', 'created_at', $end->setTimezone($utc)->format('Y-m-d H:i:s')]);
        }
    }
}
