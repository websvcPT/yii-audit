<?php

namespace websvc\audit\tests\unit\migrations;

use ReflectionMethod;
use Yii;
use yii\console\controllers\MigrateController;
use yii\di\Instance;
use yii\db\Connection;
use websvc\audit\tests\TestCase;

class MigrationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApp();
    }

    public function testUpCreatesTablesInAuditDatabase(): void
    {
        $this->migrateAudit();

        $tables = $this->auditTables();
        $this->assertSame(
            [
                [$this->auditDatabase(), 'audit_changes', 'utf8mb4_unicode_ci'],
                [$this->auditDatabase(), 'audit_requests', 'utf8mb4_unicode_ci'],
            ],
            $tables
        );

        $type = $this->db()->createCommand(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            [':schema' => $this->auditDatabase(), ':table' => 'audit_changes', ':column' => 'changes']
        )->queryScalar();
        $this->assertSame('json', $type);

        $this->assertSame([1], $this->indexUniqueness('audit_changes', 'request_id'));
        $this->assertSame([0], $this->indexUniqueness('audit_requests', 'request_id'));
    }

    public function testDownDropsTables(): void
    {
        $this->migrateAudit();
        $this->migrateAuditDown();

        $this->assertSame([], $this->auditTables());
    }

    public function testNullDatabaseCreatesTablesInConnectionDatabase(): void
    {
        $this->destroyApp();
        $this->mockConsoleApp(['database' => null]);

        $this->migrateAudit();

        $schemas = array_unique(array_column($this->auditTables(), 0));
        $this->assertSame([$this->appDatabase()], array_values($schemas));
    }

    public function testMigrationsAreDiscoveredByNamespace(): void
    {
        $this->dropAuditTables();
        $controller = new MigrateController('migrate', Yii::$app, [
            'migrationPath' => null,
            'migrationNamespaces' => ['websvc\audit\migrations'],
        ]);
        $controller->db = Instance::ensure('db', Connection::class);

        $method = new ReflectionMethod($controller, 'getNewMigrations');
        $migrations = $method->invoke($controller);

        $this->assertSame(
            [
                'websvc\audit\migrations\M261117000001CreateAuditRequests',
                'websvc\audit\migrations\M261117000002CreateAuditChanges',
            ],
            $migrations
        );
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function auditTables(): array
    {
        $rows = $this->db()->createCommand(
            "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
            WHERE TABLE_NAME IN ('audit_changes', 'audit_requests')
            AND TABLE_SCHEMA IN (:app, :audit)
            ORDER BY TABLE_SCHEMA, TABLE_NAME",
            [':app' => $this->appDatabase(), ':audit' => $this->auditDatabase()]
        )->queryAll();

        return array_map(
            static fn(array $row): array => [$row['TABLE_SCHEMA'], $row['TABLE_NAME'], $row['TABLE_COLLATION']],
            $rows
        );
    }

    /**
     * @return int[] NON_UNIQUE flags of the indexes that cover the column
     */
    private function indexUniqueness(string $table, string $column): array
    {
        $flags = $this->db()->createCommand(
            'SELECT NON_UNIQUE FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            [':schema' => $this->auditDatabase(), ':table' => $table, ':column' => $column]
        )->queryColumn();

        return array_map('intval', $flags);
    }
}
