<?php

namespace websvc\audit\tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Yii;
use yii\base\Event;
use yii\console\Application as ConsoleApplication;
use yii\db\Connection;
use yii\di\Container;
use yii\helpers\ArrayHelper;
use yii\web\Application as WebApplication;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        $this->destroyApp();
        parent::tearDown();
    }

    protected function mockConsoleApp(array $auditConfig = [], array $appConfig = []): void
    {
        new ConsoleApplication(ArrayHelper::merge($this->baseConfig($auditConfig), $appConfig));
    }

    protected function mockWebApp(array $auditConfig = [], array $appConfig = []): void
    {
        $assetPath = sys_get_temp_dir() . '/yii2-audit-assets';
        if (!is_dir($assetPath)) {
            mkdir($assetPath, 0777, true);
        }

        $webConfig = [
            'aliases' => [
                '@webroot' => sys_get_temp_dir(),
                '@web' => '/',
            ],
            'components' => [
                'request' => [
                    'cookieValidationKey' => 'test',
                    'enableCsrfValidation' => false,
                    'scriptFile' => dirname(__DIR__) . '/index.php',
                    'scriptUrl' => '/index.php',
                ],
                'user' => [
                    'identityClass' => 'websvc\audit\tests\data\models\User',
                    'enableSession' => false,
                    'loginUrl' => null,
                ],
                'assetManager' => [
                    'basePath' => $assetPath,
                    'baseUrl' => '/assets',
                ],
            ],
        ];

        new WebApplication(ArrayHelper::merge($this->baseConfig($auditConfig), $webConfig, $appConfig));
    }

    protected function destroyApp(): void
    {
        if (Yii::$app !== null && Yii::$app->has('db', true)) {
            Yii::$app->getDb()->close();
        }
        Event::offAll();
        Yii::$app = null;
        Yii::$container = new Container();
    }

    protected function db(): Connection
    {
        return Yii::$app->getDb();
    }

    protected function createTestTables(): void
    {
        $db = $this->db();
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach (['posts', 'post_tags', 'notes'] as $table) {
            $db->createCommand('DROP TABLE IF EXISTS ' . $db->quoteTableName($table))->execute();
        }
        $db->createCommand(
            'CREATE TABLE posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                status INT NOT NULL DEFAULT 0,
                price DECIMAL(10,2) NULL,
                password_hash VARCHAR(255) NULL,
                updated_at INT NULL
            ) ' . $options
        )->execute();
        $db->createCommand(
            'CREATE TABLE post_tags (
                post_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (post_id, tag_id)
            ) ' . $options
        )->execute();
        $db->createCommand(
            'CREATE TABLE notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                text VARCHAR(255) NULL
            ) ' . $options
        )->execute();
    }

    protected function auditRows(string $table, ?string $database = 'audit_log'): array
    {
        $db = $this->db();
        $name = $database === null ? $table : $database . '.' . $table;

        return $db->createCommand('SELECT * FROM ' . $db->quoteTableName($name) . ' ORDER BY id')->queryAll();
    }

    protected function appDatabase(): string
    {
        return getenv('DB_APP') ?: 'audit_app';
    }

    protected function auditDatabase(): string
    {
        return getenv('DB_AUDIT') ?: 'audit_log';
    }

    private function baseConfig(array $auditConfig): array
    {
        $host = getenv('DB_HOST') ?: 'mysql';

        return [
            'id' => 'test-app',
            'basePath' => dirname(__DIR__),
            'vendorPath' => dirname(__DIR__) . '/vendor',
            'runtimePath' => __DIR__ . '/runtime',
            'timeZone' => 'Europe/Lisbon',
            'components' => [
                'db' => [
                    'class' => Connection::class,
                    'dsn' => 'mysql:host=' . $host . ';dbname=' . $this->appDatabase(),
                    'username' => getenv('DB_USER') ?: 'audit',
                    'password' => getenv('DB_PASSWORD') ?: 'audit',
                    'charset' => 'utf8mb4',
                ],
            ],
        ];
    }
}
