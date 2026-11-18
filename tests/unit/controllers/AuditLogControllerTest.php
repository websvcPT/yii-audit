<?php

namespace websvc\audit\tests\unit\controllers;

use ReflectionMethod;
use websvc\audit\controllers\AuditLogController;
use websvc\audit\tests\data\controllers\OpenAuditLogController;
use websvc\audit\tests\data\models\Post;
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

    public function testViewSummarizesRequestAndHidesEmptyFields(): void
    {
        $this->insertAuditRow('audit_requests', [
            'request_id' => 'c1',
            'created_at' => '2026-07-01 10:00:00',
            'app_id' => 'console',
            'method' => 'CLI',
            'route' => 'import/run',
            'url' => '',
            'params' => ['args' => []],
            'status_code' => 0,
            'duration_ms' => 123,
        ]);

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'c1']);

        $this->assertStringContainsString('class="audit-log-summary"', $output);
        $this->assertStringContainsString('import/run', $output);
        $this->assertStringContainsString('123 ms', $output);
        $this->assertStringContainsString(Yii::t('audit', 'System (no user)'), $output);
        $this->assertStringNotContainsString(Yii::t('audit', '(not set)'), $output);
    }

    public function testViewShowsParamsAsKeyValueLines(): void
    {
        $this->seed();

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'r1']);

        $this->assertStringContainsString('post.Invoice.status', $output);
        $this->assertStringContainsString('post.password', $output);
    }

    public function testViewListsChangesInOneTable(): void
    {
        $this->seed();
        $this->insertAuditRow('audit_changes', [
            'request_id' => 'r1',
            'created_at' => '2026-07-01 10:00:01',
            'app_id' => 'backend',
            'action' => 'insert',
            'model_class' => 'app\models\InvoiceLine',
            'table_name' => 'invoice_lines',
            'record_id' => '7',
            'changes' => ['qty' => ['old' => null, 'new' => 3], 'price' => ['old' => null, 'new' => '9.90']],
        ]);

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'r1']);

        $this->assertSame(1, substr_count($output, 'audit-log-changes'));
        $this->assertStringContainsString('rowspan="2"', $output);
        $this->assertStringContainsString('InvoiceLine', $output);
        $this->assertStringContainsString('9.90', $output);
    }

    public function testViewUsesModelAttributeLabels(): void
    {
        $this->seed();
        $this->insertAuditRow('audit_changes', [
            'request_id' => 'r1',
            'created_at' => '2026-07-01 10:00:01',
            'app_id' => 'backend',
            'action' => 'update',
            'model_class' => Post::class,
            'table_name' => 'posts',
            'record_id' => '1',
            'changes' => ['title' => ['old' => 'A', 'new' => 'B']],
        ]);

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'r1']);

        // Generated label of the existing test model; unknown classes keep the raw name
        $this->assertStringContainsString('Title', $output);
        $this->assertStringContainsString('status', $output);
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

    public function testSubclassMayOmitReturnTypes(): void
    {
        foreach (['behaviors', 'getViewPath'] as $method) {
            $this->assertFalse((new ReflectionMethod(AuditLogController::class, $method))->hasReturnType(), $method);
        }
    }

    public function testIndexHasRequestIdFilter(): void
    {
        $this->seed();

        $output = Yii::$app->runAction('audit-log/index');

        $this->assertStringContainsString('name="AuditChangeSearch[request_id]"', $output);
    }

    public function testViewPaginatesChanges(): void
    {
        $this->seed();
        for ($i = 0; $i < 59; $i++) {
            $this->insertAuditRow('audit_changes', [
                'request_id' => 'r1',
                'created_at' => '2026-07-01 10:00:00',
                'app_id' => 'backend',
                'action' => 'insert',
                'model_class' => 'app\models\InvoiceLine',
                'table_name' => 'invoice_lines',
                'record_id' => (string) $i,
                'changes' => ['qty' => ['old' => null, 'new' => $i]],
            ]);
        }

        $output = Yii::$app->runAction('audit-log/view', ['id' => 'r1']);

        $this->assertSame(50, substr_count($output, 'class="audit-log-change"'));
    }

    public function testTimesIgnoreFormatterDefaultTimeZone(): void
    {
        Yii::$app->formatter->defaultTimeZone = 'Europe/Lisbon';
        $this->seed();

        $output = Yii::$app->runAction('audit-log/index');

        // Stored 10:00 UTC is 11:00 in Lisbon in July
        $this->assertStringContainsString('2026-07-01 11:00:00', $output);
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
