<?php

namespace websvc\audit\tests\unit\behaviors;

use websvc\audit\behaviors\AuditBehavior;
use websvc\audit\tests\data\models\Post;
use websvc\audit\tests\data\models\PostTag;
use websvc\audit\tests\data\models\User;
use websvc\audit\tests\TestCase;
use Yii;
use yii\db\Exception as DbException;
use yii\log\Logger;

class AuditBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApp();
        $this->migrateAudit();
        $this->createTestTables();
    }

    public function testInsertWritesChangeRow(): void
    {
        $post = new Post(['title' => 'A', 'status' => 1, 'updated_at' => 5]);
        $this->assertTrue($post->save(false));

        $rows = $this->auditRows('audit_changes');
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('insert', $row['action']);
        $this->assertSame(Post::class, $row['model_class']);
        $this->assertSame('posts', $row['table_name']);
        $this->assertSame((string) $post->id, $row['record_id']);
        $this->assertSame(Yii::$app->get('audit')->getRequestId(), $row['request_id']);
        $this->assertSame('test-app', $row['app_id']);

        $changes = json_decode($row['changes'], true);
        $this->assertSame('A', $changes['title']['new']);
        $this->assertArrayNotHasKey('updated_at', $changes);
    }

    public function testUpdateWritesOnlyChangedAttributes(): void
    {
        $post = new Post(['title' => 'A', 'status' => 1]);
        $post->save(false);

        $post->title = 'B';
        $post->status = '1';
        $post->updated_at = 10;
        $post->save(false);

        $rows = $this->auditRows('audit_changes');
        $this->assertCount(2, $rows);
        $this->assertSame('update', $rows[1]['action']);
        // MySQL JSON columns reorder object keys, so the comparison ignores key order
        $this->assertEquals(['title' => ['old' => 'A', 'new' => 'B']], json_decode($rows[1]['changes'], true));
    }

    public function testSaveWithoutChangesWritesNothing(): void
    {
        $post = new Post(['title' => 'A']);
        $post->save(false);
        $post->save(false);

        $this->assertCount(1, $this->auditRows('audit_changes'));
    }

    public function testDeleteKeepsOldValues(): void
    {
        $post = new Post(['title' => 'A']);
        $post->save(false);
        $id = (string) $post->id;
        $post->delete();

        $rows = $this->auditRows('audit_changes');
        $this->assertSame('delete', $rows[1]['action']);
        $this->assertSame($id, $rows[1]['record_id']);
        $this->assertSame('A', json_decode($rows[1]['changes'], true)['title']['old']);
    }

    public function testWebUserIsRecorded(): void
    {
        $this->destroyApp();
        $this->mockWebApp();
        Yii::$app->user->setIdentity(new User(['id' => 7, 'username' => 'ana']));

        (new Post(['title' => 'A']))->save(false);

        $row = $this->auditRows('audit_changes')[0];
        $this->assertSame('7', $row['user_id']);
        $this->assertSame('ana', $row['user_name']);
    }

    public function testUserNameCallbackIsUsed(): void
    {
        $this->destroyApp();
        $this->mockWebApp(['userNameCallback' => static fn(User $identity): string => 'user-' . $identity->getId()]);
        Yii::$app->user->setIdentity(new User(['id' => 7, 'username' => 'ana']));

        (new Post(['title' => 'A']))->save(false);

        $this->assertSame('user-7', $this->auditRows('audit_changes')[0]['user_name']);
    }

    public function testConsoleHasNoUser(): void
    {
        (new Post(['title' => 'A']))->save(false);

        $row = $this->auditRows('audit_changes')[0];
        $this->assertNull($row['user_id']);
        $this->assertNull($row['user_name']);
    }

    public function testRollbackRemovesChangeRows(): void
    {
        $transaction = $this->db()->beginTransaction();
        (new Post(['title' => 'A']))->save(false);
        $transaction->rollBack();

        $this->assertSame([], $this->auditRows('audit_changes'));
        $this->assertSame(0, (int) Post::find()->count());
    }

    public function testNestedRollbackRemovesOnlyInnerRows(): void
    {
        $outer = $this->db()->beginTransaction();
        (new Post(['title' => 'A']))->save(false);
        $inner = $this->db()->beginTransaction();
        (new Post(['title' => 'B']))->save(false);
        $inner->rollBack();
        $outer->commit();

        $rows = $this->auditRows('audit_changes');
        $this->assertCount(1, $rows);
        $this->assertSame('A', json_decode($rows[0]['changes'], true)['title']['new']);
    }

    public function testCompositePrimaryKeyIsJson(): void
    {
        $tag = new PostTag(['post_id' => 1, 'tag_id' => 2]);
        $tag->attachBehavior('audit', AuditBehavior::class);
        $tag->save(false);

        $this->assertSame('{"post_id":1,"tag_id":2}', $this->auditRows('audit_changes')[0]['record_id']);
    }

    public function testAuditFailureDoesNotBreakSave(): void
    {
        $this->db()->createCommand('DROP TABLE ' . $this->auditDatabase() . '.audit_changes')->execute();

        $this->assertTrue((new Post(['title' => 'A']))->save(false));
        $errors = array_filter(
            Yii::getLogger()->messages,
            static fn(array $message): bool => $message[1] === Logger::LEVEL_ERROR && $message[2] === 'audit'
        );
        $this->assertNotEmpty($errors);

        $this->destroyApp();
        $this->mockConsoleApp(['failSilently' => false]);
        $this->expectException(DbException::class);
        (new Post(['title' => 'B']))->save(false);
    }

    public function testBehaviorListsMergeWithComponentLists(): void
    {
        $this->destroyApp();
        $this->mockConsoleApp(['ignoreAttributes' => ['body']]);

        (new Post(['title' => 'A', 'body' => 'text', 'password_hash' => 'secret', 'updated_at' => 5]))->save(false);

        $changes = json_decode($this->auditRows('audit_changes')[0]['changes'], true);
        $this->assertSame('***', $changes['password_hash']['new']);
        $this->assertArrayNotHasKey('body', $changes);
        $this->assertArrayNotHasKey('updated_at', $changes);
    }
}
