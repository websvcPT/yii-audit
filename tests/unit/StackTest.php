<?php

namespace websvc\audit\tests\unit;

use websvc\audit\tests\TestCase;

class StackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleApp();
    }

    public function testDatabasesAreReachable(): void
    {
        $db = $this->db();

        $this->assertSame($this->appDatabase(), $db->createCommand('SELECT DATABASE()')->queryScalar());

        $probe = $db->quoteTableName($this->auditDatabase() . '.stack_probe');
        $db->createCommand('CREATE TABLE IF NOT EXISTS ' . $probe . ' (id INT)')->execute();
        $db->createCommand('DROP TABLE ' . $probe)->execute();
    }

    public function testNestedRollbackSpansDatabases(): void
    {
        $db = $this->db();
        $tableA = $db->quoteTableName($this->appDatabase() . '.stack_a');
        $tableB = $db->quoteTableName($this->auditDatabase() . '.stack_b');
        $db->createCommand('CREATE TABLE IF NOT EXISTS ' . $tableA . ' (id INT) ENGINE=InnoDB')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS ' . $tableB . ' (id INT) ENGINE=InnoDB')->execute();

        try {
            $outer = $db->beginTransaction();
            $db->createCommand('INSERT INTO ' . $tableA . ' VALUES (1)')->execute();
            $inner = $db->beginTransaction();
            $db->createCommand('INSERT INTO ' . $tableB . ' VALUES (1)')->execute();
            $inner->rollBack();
            $outer->commit();

            $this->assertSame(1, (int) $db->createCommand('SELECT COUNT(*) FROM ' . $tableA)->queryScalar());
            $this->assertSame(0, (int) $db->createCommand('SELECT COUNT(*) FROM ' . $tableB)->queryScalar());
        } finally {
            $db->createCommand('DROP TABLE IF EXISTS ' . $tableA)->execute();
            $db->createCommand('DROP TABLE IF EXISTS ' . $tableB)->execute();
        }
    }
}
