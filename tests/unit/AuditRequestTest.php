<?php

namespace websvc\audit\tests\unit;

use websvc\audit\tests\data\models\Post;
use websvc\audit\tests\TestCase;
use Yii;
use yii\base\Application;
use yii\web\Response;

class AuditRequestTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function testPostWritesRequestRow(): void
    {
        $this->web('POST', 'post/create');
        Yii::$app->request->setBodyParams(['Post' => ['title' => 'A'], 'password' => 'x']);
        Yii::$app->request->setQueryParams(['id' => '5']);
        Yii::$app->request->setUrl('/post/create?id=5');

        $this->send(302);

        $rows = $this->auditRows('audit_requests');
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('POST', $row['method']);
        $this->assertSame('post/create', $row['route']);
        $this->assertSame('/post/create', $row['url']);
        $this->assertSame(302, (int) $row['status_code']);
        $this->assertSame('phpunit', $row['user_agent']);
        $this->assertSame('198.51.100.7', $row['ip']);
        $this->assertSame('test-app', $row['app_id']);
        $this->assertSame(Yii::$app->get('audit')->getRequestId(), $row['request_id']);
        $this->assertLessThan(60, abs(strtotime($row['created_at'] . ' UTC') - time()));

        $params = json_decode($row['params'], true);
        $this->assertSame('***', $params['post']['password']);
        $this->assertSame('A', $params['post']['Post']['title']);
        $this->assertSame('5', $params['get']['id']);
        $this->assertSame([], $params['files']);
    }

    public function testGetWithoutChangesWritesNothing(): void
    {
        $this->web('GET', 'post/index');

        $this->send(200);

        $this->assertSame([], $this->auditRows('audit_requests'));
    }

    public function testGetWithChangesWritesRow(): void
    {
        $this->web('GET', 'post/touch');
        (new Post(['title' => 'A']))->save(false);

        $this->send(200);

        $rows = $this->auditRows('audit_requests');
        $this->assertCount(1, $rows);
        $this->assertSame('GET', $rows[0]['method']);
    }

    public function testIgnoredRouteWritesNothing(): void
    {
        $this->web('POST', 'debug/default/index');

        $this->send(200);

        $this->assertSame([], $this->auditRows('audit_requests'));
    }

    public function testRowIsWrittenOnce(): void
    {
        $this->web('POST', 'post/create');

        Yii::$app->get('audit')->recordRequest();
        Yii::$app->get('audit')->recordRequest();
        $this->send(200);

        $this->assertCount(1, $this->auditRows('audit_requests'));
        $this->assertTrue(Yii::$app->get('audit')->isRequestRecorded());
    }

    public function testErrorStatusIsRecorded(): void
    {
        $this->web('POST', 'post/create');

        $this->send(500);

        $this->assertSame(500, (int) $this->auditRows('audit_requests')[0]['status_code']);
    }

    public function testIpComesFromCallbackWhenSet(): void
    {
        $this->web('POST', 'post/create', ['ipCallback' => static fn(): string => '203.0.113.9']);

        $this->send(200);

        $this->assertSame('203.0.113.9', $this->auditRows('audit_requests')[0]['ip']);
    }

    public function testLongValuesAreTruncated(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('u', 400);
        $this->web('POST', 'post/create', [], false);
        Yii::$app->request->setUrl('/' . str_repeat('p', 3000));

        $this->send(200);

        $row = $this->auditRows('audit_requests')[0];
        $this->assertSame(255, strlen($row['user_agent']));
        $this->assertSame(2048, strlen($row['url']));
    }

    public function testConsoleRunWithChangesWritesCliRow(): void
    {
        $this->console('import/run');
        Yii::$app->request->setParams(['--api_key=abc']);
        (new Post(['title' => 'A']))->save(false);

        $this->finishConsole();

        $row = $this->auditRows('audit_requests')[0];
        $this->assertSame('CLI', $row['method']);
        $this->assertSame('import/run', $row['route']);
        $this->assertSame(0, (int) $row['status_code']);
        $this->assertSame(['--api_key=***'], json_decode($row['params'], true)['args']);
    }

    public function testConsoleRunWithoutChangesWritesNothing(): void
    {
        $this->console('import/run');

        $this->finishConsole();

        $this->assertSame([], $this->auditRows('audit_requests'));
    }

    public function testCliInTrackMethodsAlwaysWritesRow(): void
    {
        $this->console('import/run', ['trackMethods' => ['CLI']]);

        $this->finishConsole();

        $this->assertCount(1, $this->auditRows('audit_requests'));
    }

    public function testNullDatabaseWritesToConnectionDatabase(): void
    {
        $this->web('POST', 'post/create', ['database' => null]);

        $this->send(200);

        $this->assertCount(1, $this->auditRows('audit_requests', null));
    }

    private function web(string $method, string $route, array $auditConfig = [], bool $defaultAgent = true): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        if ($defaultAgent) {
            $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        }
        $this->mockWebApp($auditConfig + ['failSilently' => false]);
        $this->migrateAudit();
        $this->createTestTables();
        Yii::$app->requestedRoute = $route;
    }

    private function send(int $status): void
    {
        $response = Yii::$app->getResponse();
        $response->statusCode = $status;
        $response->trigger(Response::EVENT_AFTER_SEND);
    }

    private function console(string $route, array $auditConfig = []): void
    {
        $this->mockConsoleApp($auditConfig + ['failSilently' => false]);
        $this->migrateAudit();
        $this->createTestTables();
        Yii::$app->requestedRoute = $route;
        Yii::$app->request->setParams([]);
    }

    private function finishConsole(): void
    {
        Yii::$app->getResponse()->exitStatus = 0;
        Yii::$app->trigger(Application::EVENT_AFTER_REQUEST);
    }
}
