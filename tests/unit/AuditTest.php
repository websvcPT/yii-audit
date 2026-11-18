<?php

namespace websvc\audit\tests\unit;

use websvc\audit\Audit;
use websvc\audit\tests\TestCase;

class AuditTest extends TestCase
{
    public function testTableNameQualifiesWithDatabase(): void
    {
        $audit = new Audit(['database' => 'audit_log']);
        $this->assertSame('audit_log.audit_changes', $audit->tableName(Audit::TABLE_CHANGES));

        $audit = new Audit(['database' => null]);
        $this->assertSame('audit_changes', $audit->tableName(Audit::TABLE_CHANGES));
    }

    public function testRequestIdIsStableHex(): void
    {
        $audit = new Audit();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $audit->getRequestId());
        $this->assertSame($audit->getRequestId(), $audit->getRequestId());
    }
}
