<?php

namespace websvc\audit\tests\unit;

use websvc\audit\tests\data\models\Note;
use websvc\audit\tests\data\models\Post;
use websvc\audit\tests\TestCase;

class AuditGlobalTrackingTest extends TestCase
{
    public function testTracksModelsWithoutBehavior(): void
    {
        $this->boot(['trackAllModels' => true]);

        (new Note(['text' => 'hello']))->save(false);

        $rows = $this->auditRows('audit_changes');
        $this->assertCount(1, $rows);
        $this->assertSame(Note::class, $rows[0]['model_class']);
    }

    public function testExcludedModelsAreSkipped(): void
    {
        $this->boot(['trackAllModels' => true, 'excludeModels' => [Note::class]]);

        (new Note(['text' => 'hello']))->save(false);

        $this->assertSame([], $this->auditRows('audit_changes'));
    }

    public function testModelWithBehaviorIsRecordedOnce(): void
    {
        $this->boot(['trackAllModels' => true]);

        $post = new Post(['title' => 'A']);
        $post->save(false);
        $post->title = 'B';
        $post->updated_at = 10;
        $post->save(false);

        $rows = $this->auditRows('audit_changes');
        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey('updated_at', json_decode($rows[1]['changes'], true));
    }

    public function testDisabledModeTracksNothing(): void
    {
        $this->boot([]);

        (new Note(['text' => 'hello']))->save(false);

        $this->assertSame([], $this->auditRows('audit_changes'));
    }

    private function boot(array $auditConfig): void
    {
        $this->mockConsoleApp($auditConfig);
        $this->migrateAudit();
        $this->createTestTables();
    }
}
