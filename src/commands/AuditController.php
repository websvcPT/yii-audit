<?php

namespace websvc\audit\commands;

use websvc\audit\Audit;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Maintenance commands for the audit tables.
 *
 * Map it in the console application, for example `'controllerMap' => ['audit' => AuditController::class]`.
 */
class AuditController extends Controller
{
    /**
     * Rows deleted per statement, so cleanup never holds long locks.
     */
    public int $batchSize = 1000;

    /**
     * Deletes change and request rows older than the given number of days.
     *
     * @param int $days rows older than this many days are deleted
     */
    public function actionCleanup(int $days = 365): int
    {
        if ($days < 1) {
            $this->stderr(Yii::t('audit', 'Days must be 1 or more.') . "\n");

            return ExitCode::USAGE;
        }

        $audit = Audit::getInstance();
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        foreach ([Audit::TABLE_CHANGES, Audit::TABLE_REQUESTS] as $table) {
            $deleted = $this->deleteOlderThan($audit, $table, $cutoff);
            $this->stdout(Yii::t('audit', '{table}: {count} rows deleted', [
                'table' => $table,
                'count' => $deleted,
            ]) . "\n");
        }

        return ExitCode::OK;
    }

    private function deleteOlderThan(Audit $audit, string $table, string $cutoff): int
    {
        $db = $audit->getDb();
        $sql = 'DELETE FROM ' . $db->quoteTableName($audit->tableName($table))
            . ' WHERE created_at < :cutoff ORDER BY id LIMIT ' . max(1, $this->batchSize);

        $total = 0;
        do {
            $count = $db->createCommand($sql, [':cutoff' => $cutoff])->execute();
            $total += $count;
        } while ($count >= max(1, $this->batchSize));

        return $total;
    }
}
