<?php

namespace websvc\audit\migrations;

use websvc\audit\Audit;

class M261117000001CreateAuditRequests extends AuditMigration
{
    public function up()
    {
        $table = $this->auditTable(Audit::TABLE_REQUESTS);

        $this->createTable($table, [
            'id' => $this->bigPrimaryKey(),
            'request_id' => $this->char(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'app_id' => $this->string(64)->notNull(),
            'user_id' => $this->string(64)->null(),
            'user_name' => $this->string(255)->null(),
            'ip' => $this->string(45)->null(),
            'method' => $this->string(10)->notNull(),
            'route' => $this->string(255)->notNull(),
            'url' => $this->string(2048)->notNull(),
            'params' => $this->json()->null(),
            'status_code' => $this->smallInteger()->null(),
            'duration_ms' => $this->integer()->null(),
            'user_agent' => $this->string(255)->null(),
        ], $this->tableOptions());

        $this->createIndex('ux_audit_requests_request_id', $table, 'request_id', true);
        $this->createIndex('ix_audit_requests_created_at', $table, 'created_at');
        $this->createIndex('ix_audit_requests_app_user', $table, ['app_id', 'user_id']);
        $this->createIndex('ix_audit_requests_route', $table, 'route');
        $this->createIndex('ix_audit_requests_status_code', $table, 'status_code');
    }

    public function down()
    {
        $this->dropTable($this->auditTable(Audit::TABLE_REQUESTS));
    }
}
