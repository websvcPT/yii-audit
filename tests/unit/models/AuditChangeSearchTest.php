<?php

namespace websvc\audit\tests\unit\models;

use PHPUnit\Framework\Attributes\DataProvider;
use websvc\audit\models\AuditChange;
use websvc\audit\models\AuditChangeSearch;
use websvc\audit\tests\TestCase;

class AuditChangeSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApp();
        $this->migrateAudit();

        $rows = [
            [1, '7', 'ana', 'app-a', 'insert', 'app\models\Post', '1', 'r1', ['title'], '2026-07-01 23:30:00'],
            [2, '8', 'bruno', 'app-b', 'update', 'app\models\Note', '2', 'r2', ['body'], '2026-07-03 10:00:00'],
            [3, '9', 'diana', 'app-a', 'delete', 'app\models\Post', '3', 'r3', ['title', 'body'], '2026-06-30 10:00:00'],
        ];
        foreach ($rows as [$id, $userId, $userName, $appId, $action, $class, $recordId, $requestId, $fields, $at]) {
            $changes = [];
            foreach ($fields as $field) {
                $changes[$field] = ['old' => null, 'new' => 'x'];
            }
            $this->insertAuditRow('audit_changes', [
                'id' => $id,
                'request_id' => $requestId,
                'created_at' => $at,
                'app_id' => $appId,
                'user_id' => $userId,
                'user_name' => $userName,
                'action' => $action,
                'model_class' => $class,
                'table_name' => 'posts',
                'record_id' => $recordId,
                'changes' => $changes,
            ]);
        }
    }

    public static function filterProvider(): array
    {
        return [
            'user by id' => [['user' => '7'], [1]],
            'user by name fragment' => [['user' => 'an'], [3, 1]],
            'app' => [['app_id' => 'app-a'], [3, 1]],
            'action' => [['action' => 'update'], [2]],
            'model' => [['model_class' => 'Post'], [3, 1]],
            'record id' => [['record_id' => '2'], [2]],
            'request id' => [['request_id' => 'r3'], [3]],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testFiltersNarrowResults(array $filter, array $expectedIds): void
    {
        $this->assertSame($expectedIds, $this->ids($filter));
    }

    public function testDateFilterUsesAppTimeZone(): void
    {
        $this->assertSame([2, 1], $this->ids(['created_from' => '2026-07-02']));
        $this->assertSame([3], $this->ids(['created_to' => '2026-07-01']));
    }

    public function testFieldFilterMatchesAttributeName(): void
    {
        $this->assertSame([3, 1], $this->ids(['field' => 'title']));
        $this->assertSame([3, 2], $this->ids(['field' => 'body']));
    }

    public function testFieldFilterIsNotInjectable(): void
    {
        $this->assertSame([], $this->ids(['field' => "a\"') OR 1=1 -- "]));
    }

    public function testNewestFirstAndPageSize(): void
    {
        $provider = (new AuditChangeSearch())->search([]);

        $this->assertSame([3, 2, 1], array_map('intval', array_column($provider->getModels(), 'id')));
        $this->assertSame(50, $provider->getPagination()->getPageSize());
    }

    public function testAuditModelsAreNeverTracked(): void
    {
        $this->destroyApp();
        $this->mockConsoleApp(['trackAllModels' => true]);
        $this->migrateAudit();

        $change = new AuditChange([
            'request_id' => 'r9',
            'created_at' => '2026-07-01 10:00:00',
            'app_id' => 'app-a',
            'action' => 'insert',
            'model_class' => 'app\models\Post',
            'table_name' => 'posts',
            'changes' => ['title' => ['old' => null, 'new' => 'x']],
        ]);
        $this->assertTrue($change->save(false));

        $this->assertCount(1, $this->auditRows('audit_changes'));
        $this->assertSame(['title'], $change->getChangedAttributeNames());
    }

    private function ids(array $filter): array
    {
        $provider = (new AuditChangeSearch())->search(['AuditChangeSearch' => $filter]);

        return array_map('intval', array_column($provider->getModels(), 'id'));
    }
}
