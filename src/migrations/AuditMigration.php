<?php

namespace websvc\audit\migrations;

use websvc\audit\Audit;
use yii\db\Migration;

/**
 * Base class for the package migrations. Table names follow the `audit` component's database setting.
 */
abstract class AuditMigration extends Migration
{
    protected function auditTable(string $table): string
    {
        return Audit::getInstance()->tableName($table);
    }

    protected function tableOptions(): ?string
    {
        if ($this->db->driverName === 'mysql') {
            return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        return null;
    }
}
