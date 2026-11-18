<?php

namespace websvc\audit\controllers;

use websvc\audit\models\AuditChange;
use websvc\audit\models\AuditChangeSearch;
use websvc\audit\models\AuditRequest;
use websvc\audit\models\AuditRequestSearch;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Audit log grids and request detail page.
 *
 * Access is denied to everyone. Host applications extend this controller and replace `behaviors()` with their own
 * access rules, or map it in `controllerMap` with an `as access` behavior. `behaviors()` and `getViewPath()` declare
 * no return type, so subclasses may override them without one.
 */
class AuditLogController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => false],
                ],
            ],
        ];
    }

    /**
     * Changes grid.
     */
    public function actionIndex(): string
    {
        $searchModel = new AuditChangeSearch();
        $dataProvider = $searchModel->search($this->request->getQueryParams());

        return $this->render('index', ['searchModel' => $searchModel, 'dataProvider' => $dataProvider]);
    }

    /**
     * Requests grid.
     */
    public function actionRequests(): string
    {
        $searchModel = new AuditRequestSearch();
        $dataProvider = $searchModel->search($this->request->getQueryParams());

        return $this->render('requests', ['searchModel' => $searchModel, 'dataProvider' => $dataProvider]);
    }

    /**
     * Request detail with its changes, 50 per page.
     *
     * @param string $id request id
     * @throws NotFoundHttpException when neither a request row nor change rows exist for the id
     */
    public function actionView(string $id): string
    {
        $request = AuditRequest::findOne(['request_id' => $id]);
        $changes = new ActiveDataProvider([
            'query' => AuditChange::find()->where(['request_id' => $id]),
            'sort' => ['attributes' => ['id'], 'defaultOrder' => ['id' => SORT_ASC]],
            'pagination' => ['pageSize' => 50],
        ]);
        if ($request === null && $changes->getTotalCount() === 0) {
            throw new NotFoundHttpException(Yii::t('audit', 'The requested audit entry does not exist.'));
        }

        return $this->render('view', ['id' => $id, 'request' => $request, 'changes' => $changes]);
    }

    /**
     * Views are read from the package, so subclasses in host applications need no copies.
     *
     * @return string
     */
    public function getViewPath()
    {
        return dirname(__DIR__) . '/views/audit-log';
    }
}
