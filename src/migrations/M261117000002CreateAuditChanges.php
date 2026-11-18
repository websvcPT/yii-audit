<?php

namespace websvc\audit\migrations;

use websvc\audit\Audit;

class M261117000002CreateAuditChanges extends AuditMigration
{
    public function up()
    {
        $table = $this->auditTable(Audit::TABLE_CHANGES);

        $this->createTable($table, [
            'id' => $this->bigPrimaryKey(),
            'request_id' => $this->char(32)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'app_id' => $this->string(64)->notNull(),
            'user_id' => $this->string(64)->null(),
            'user_name' => $this->string(255)->null(),
            'action' => $this->string(6)->notNull(),
            'model_class' => $this->string(255)->notNull(),
            'table_name' => $this->string(255)->notNull(),
            'record_id' => $this->string(255)->null(),
            'changes' => $this->json()->notNull(),
        ], $this->tableOptions());

        $this->createIndex('ix_audit_changes_request_id', $table, 'request_id');
        $this->createIndex('ix_audit_changes_created_at', $table, 'created_at');
        $this->createIndex('ix_audit_changes_model_record', $table, ['model_class', 'record_id']);
        $this->createIndex('ix_audit_changes_app_user', $table, ['app_id', 'user_id']);
        $this->createIndex('ix_audit_changes_table_name', $table, 'table_name');
    }

    public function down()
    {
        $this->dropTable($this->auditTable(Audit::TABLE_CHANGES));
    }
}
