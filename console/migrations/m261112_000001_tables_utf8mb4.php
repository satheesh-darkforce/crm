<?php

use yii\db\Migration;

/**
 * Converts every table (and the database default) to utf8mb4.
 *
 * 91 tables were latin1, which turned ₹ into "?" and cannot hold Kannada, Tamil or
 * Hindi text. CONVERT TO CHARACTER SET re-encodes existing text, so data is kept.
 * Tables already on utf8mb4 are skipped; safe to re-run.
 */
class m261112_000001_tables_utf8mb4 extends Migration
{
    private const CHARSET   = 'utf8mb4';
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function safeUp()
    {
        $db = $this->db;
        $dbName = $db->createCommand('SELECT DATABASE()')->queryScalar();
        $this->execute('ALTER DATABASE ' . $db->quoteTableName($dbName) . ' CHARACTER SET ' . self::CHARSET . ' COLLATE ' . self::COLLATION);

        $tables = $db->createCommand(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :db AND TABLE_TYPE = 'BASE TABLE' AND TABLE_COLLATION NOT LIKE 'utf8mb4%'",
            [':db' => $dbName]
        )->queryColumn();

        $this->execute('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->execute('ALTER TABLE ' . $db->quoteTableName($table)
                . ' CONVERT TO CHARACTER SET ' . self::CHARSET . ' COLLATE ' . self::COLLATION);
        }
        $this->execute('SET FOREIGN_KEY_CHECKS = 1');
        echo '    > converted ' . count($tables) . " table(s) to utf8mb4\n";
    }

    public function safeDown()
    {
        echo "    > not reverted: converting back to latin1 would lose characters.\n";
        return true;
    }
}
