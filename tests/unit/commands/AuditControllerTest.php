<?php

namespace websvc\audit\tests\unit\commands;

use websvc\audit\tests\data\controllers\QuietAuditController;
use websvc\audit\tests\TestCase;
use Yii;
use yii\console\ExitCode;

class AuditControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApp();
        $this->migrateAudit();
    }

    public function testCleanupDeletesOnlyOldRows(): void
    {
        foreach ([400, 10] as $days) {
            $this->insertChange($days, 'c' . $days);
            $this->insertRequest($days, 'q' . $days);
        }

        $controller = new QuietAuditController('audit', Yii::$app);
        $this->assertSame(ExitCode::OK, $controller->actionCleanup(365));

        $this->assertSame(['c10'], array_column($this->auditRows('audit_changes'), 'request_id'));
        $this->assertSame(['q10'], array_column($this->auditRows('audit_requests'), 'request_id'));
    }

    public function testCleanupHandlesMoreThanOneBatch(): void
    {
        $rows = [];
        $old = gmdate('Y-m-d H:i:s', time() - 400 * 86400);
        for ($i = 0; $i < 2500; $i++) {
            $rows[] = ['r' . $i, $old, 'backend', 'insert', 'app\models\Invoice', 'invoices', '{"a":1}'];
        }
        $this->db()->createCommand()->batchInsert(
            $this->auditDatabase() . '.audit_changes',
            ['request_id', 'created_at', 'app_id', 'action', 'model_class', 'table_name', 'changes'],
            $rows
        )->execute();

        (new QuietAuditController('audit', Yii::$app))->actionCleanup(365);

        $this->assertSame([], $this->auditRows('audit_changes'));
    }

    public function testRejectsDaysBelowOne(): void
    {
        $this->insertChange(400, 'c400');

        $controller = new QuietAuditController('audit', Yii::$app);
        $this->assertSame(ExitCode::USAGE, $controller->actionCleanup(0));

        $this->assertCount(1, $this->auditRows('audit_changes'));
    }

    private function insertChange(int $daysAgo, string $requestId): void
    {
        $this->insertAuditRow('audit_changes', [
            'request_id' => $requestId,
            'created_at' => gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400),
            'app_id' => 'backend',
            'action' => 'insert',
            'model_class' => 'app\models\Invoice',
            'table_name' => 'invoices',
            'changes' => ['a' => ['old' => null, 'new' => 1]],
        ]);
    }

    private function insertRequest(int $daysAgo, string $requestId): void
    {
        $this->insertAuditRow('audit_requests', [
            'request_id' => $requestId,
            'created_at' => gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400),
            'app_id' => 'backend',
            'method' => 'POST',
            'route' => 'invoice/update',
            'url' => '/invoice/update',
        ]);
    }
}
