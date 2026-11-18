<?php

namespace websvc\audit\tests\unit\controllers;

use websvc\audit\controllers\AuditLogController;
use websvc\audit\tests\data\controllers\OpenAuditLogController;
use websvc\audit\tests\TestCase;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class AuditLogControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->boot(OpenAuditLogController::class);
    }

    public function testBaseControllerDeniesEveryone(): void
    {
        $this->destroyApp();
        $this->boot(AuditLogController::class);

        $this->expectException(ForbiddenHttpException::class);
        Yii::$app->runAction('audit-log/index');
    }

    public function testIndexRendersChangesGrid(): void
    {
        $this->seed();

        $output = Yii::$app->runAction('audit-log/index');

        $this->assertStringContainsString('Invoice', $output);
        $this->assertStringContainsString('name="AuditChangeSearch[user]"', $output);
    }

    public function testRequestsRendersRequestsGrid(): void
    {
        $this->seed();

        $output = Yii::$app->runAction('audit-log/requests');

        $this->assertStringContainsString('invoice/update', $output);
        $this->assertStringContainsString('name="AuditRequestSearch[route]"', $output);
    }

    public function testViewShowsRequestAndChanges(): void
    {
        $this->seed();

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'r1']);

        $this->assertStringContainsString('invoice/update', $output);
        $this->assertStringContainsString('Draft', $output);
        $this->assertStringContainsString('Paid', $output);
        $this->assertStringContainsString('***', $output);
    }

    public function testViewUnknownIdThrowsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('audit-log/view', ['id' => 'missing']);
    }

    public function testViewEscapesValues(): void
    {
        $this->seed('<script>alert(1)</script>');

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'r1']);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
        $this->assertStringNotContainsString('<script>alert(1)', $output);
    }

    public function testPortugueseTranslation(): void
    {
        Yii::$app->language = 'pt-PT';
        $this->seed();

        $output = Yii::$app->runAction('audit-log/index');

        $this->assertStringContainsString('Alterações', $output);
    }

    private function boot(string $controllerClass): void
    {
        $this->mockWebApp([], ['controllerMap' => ['audit-log' => $controllerClass]]);
        Yii::$app->request->setUrl('/audit-log/index');
        $this->migrateAudit();
    }

    private function seed(string $newValue = 'Paid'): void
    {
        $this->insertAuditRow('audit_requests', [
            'request_id' => 'r1',
            'created_at' => '2026-07-01 10:00:00',
            'app_id' => 'backend',
            'user_id' => '7',
            'user_name' => 'ana',
            'ip' => '198.51.100.7',
            'method' => 'POST',
            'route' => 'invoice/update',
            'url' => '/invoice/update',
            'params' => ['post' => ['password' => '***', 'Invoice' => ['status' => 'Paid']]],
            'status_code' => 302,
            'duration_ms' => 12,
        ]);
        $this->insertAuditRow('audit_changes', [
            'request_id' => 'r1',
            'created_at' => '2026-07-01 10:00:00',
            'app_id' => 'backend',
            'user_id' => '7',
            'user_name' => 'ana',
            'action' => 'update',
            'model_class' => 'app\models\Invoice',
            'table_name' => 'invoices',
            'record_id' => '42',
            'changes' => ['status' => ['old' => 'Draft', 'new' => $newValue]],
        ]);
    }
}
