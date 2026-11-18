<?php

namespace websvc\audit\tests\unit\models;

use PHPUnit\Framework\Attributes\DataProvider;
use websvc\audit\models\AuditRequestSearch;
use websvc\audit\tests\TestCase;

class AuditRequestSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApp();
        $this->migrateAudit();

        $rows = [
            [1, '7', 'ana', 'app-a', 'POST', 'post/create', 302, '198.51.100.7', '2026-07-01 23:30:00'],
            [2, '8', 'bruno', 'app-b', 'GET', 'post/index', 200, '203.0.113.9', '2026-07-03 10:00:00'],
            [3, '9', 'diana', 'app-a', 'CLI', 'import/run', 0, null, '2026-06-30 10:00:00'],
        ];
        foreach ($rows as [$id, $userId, $userName, $appId, $method, $route, $status, $ip, $at]) {
            $this->insertAuditRow('audit_requests', [
                'id' => $id,
                'request_id' => 'r' . $id,
                'created_at' => $at,
                'app_id' => $appId,
                'user_id' => $userId,
                'user_name' => $userName,
                'ip' => $ip,
                'method' => $method,
                'route' => $route,
                'url' => '/' . $route,
                'params' => ['post' => []],
                'status_code' => $status,
            ]);
        }
    }

    public static function filterProvider(): array
    {
        return [
            'user by id' => [['user' => '7'], [1]],
            'user by name fragment' => [['user' => 'an'], [3, 1]],
            'app' => [['app_id' => 'app-a'], [3, 1]],
            'method' => [['method' => 'GET'], [2]],
            'route fragment' => [['route' => 'post'], [2, 1]],
            'status zero' => [['status_code' => '0'], [3]],
            'ip' => [['ip' => '203.0.113.9'], [2]],
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

    public function testInvalidDateReturnsNothing(): void
    {
        $this->assertSame([], $this->ids(['created_from' => 'not-a-date']));
    }

    public function testNewestFirstAndPageSize(): void
    {
        $provider = (new AuditRequestSearch())->search([]);

        $this->assertSame([3, 2, 1], array_map('intval', array_column($provider->getModels(), 'id')));
        $this->assertSame(50, $provider->getPagination()->getPageSize());
    }

    private function ids(array $filter): array
    {
        $provider = (new AuditRequestSearch())->search(['AuditRequestSearch' => $filter]);

        return array_map('intval', array_column($provider->getModels(), 'id'));
    }
}
