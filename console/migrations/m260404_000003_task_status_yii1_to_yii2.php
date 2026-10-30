<?php

use yii\db\Migration;

/**
 * Data migration: Convert Yii1 task_status codes to Yii2 task_status codes.
 *
 * ⚠️  REVIEW BEFORE RUNNING: This migration alters live data in the `task` table.
 *     Only run this when importing Yii1 data into the Yii2 database for the first time.
 *     If the Yii2 database already has live data created via the Yii2 app, do NOT run this.
 *
 * Status mapping:
 * ┌─────────────────────────┬────────────┬────────────┐
 * │ Meaning                 │ Yii1 code  │ Yii2 code  │
 * ├─────────────────────────┼────────────┼────────────┤
 * │ New / Unread            │ 10         │ 1 (Pending)│
 * │ Assigned (queue)        │ 20         │ 1 (Pending)│
 * │ Pending                 │ 1          │ 1          │
 * │ Accepted / On Hold      │ 2          │ 3 (Stopped)│
 * │ On Going                │ 3          │ 2 (Started)│
 * │ Completed               │ 5          │ 4          │
 * │ Re-Assigned/Closed      │ 6          │ 5 (Closed) │
 * └─────────────────────────┴────────────┴────────────┘
 *
 * PmTask status mapping (same table):
 * ┌─────────────────────────┬────────────┬────────────┐
 * │ Meaning                 │ Yii1 code  │ Yii2 code  │
 * ├─────────────────────────┼────────────┼────────────┤
 * │ Pending                 │ 1          │ 1          │
 * │ Accepted                │ 2          │ 2          │
 * │ On Going                │ 3          │ 3          │
 * │ Completed               │ 5          │ 4          │
 * │ Re-Assigned             │ 6          │ 5 (Closed) │
 * └─────────────────────────┴────────────┴────────────┘
 *
 * Note: The `task` table is shared by both Task and PmTask models.
 * The mapping above attempts to be compatible with both, but ambiguous cases
 * (e.g. Yii1 status=2 meaning "Accepted" for PM vs "On Hold" for regular tasks)
 * must be reviewed manually per project type.
 */
class m260404_000003_task_status_yii1_to_yii2 extends Migration
{
    public function safeUp()
    {
        // Step 1: Use a temporary column to stage new values safely
        $this->addColumn('task', 'task_status_new', $this->tinyInteger()->defaultValue(1));

        // Step 2: Map old → new status codes
        // ORDER MATTERS: process higher Yii1 codes first to avoid double-mapping

        // Yii1 status=10 (New/Unread) → Yii2 status=1 (Pending)
        $this->execute("UPDATE task SET task_status_new = 1 WHERE task_status = 10");

        // Yii1 status=20 (Assigned queue) → Yii2 status=1 (Pending)
        $this->execute("UPDATE task SET task_status_new = 1 WHERE task_status = 20");

        // Yii1 status=6 (Re-Assigned/Closed) → Yii2 status=5 (Closed)
        $this->execute("UPDATE task SET task_status_new = 5 WHERE task_status = 6");

        // Yii1 status=5 (Completed) → Yii2 status=4 (Completed)
        $this->execute("UPDATE task SET task_status_new = 4 WHERE task_status = 5");

        // Yii1 status=3 (On Going) → Yii2 status=2 (Started)
        $this->execute("UPDATE task SET task_status_new = 2 WHERE task_status = 3");

        // Yii1 status=2 (Accepted/On Hold) → Yii2 status=3 (Stopped — closest match)
        $this->execute("UPDATE task SET task_status_new = 3 WHERE task_status = 2");

        // Yii1 status=1 (Pending) → Yii2 status=1 (Pending — no change)
        $this->execute("UPDATE task SET task_status_new = 1 WHERE task_status = 1");

        // Step 3: Swap columns
        $this->execute("UPDATE task SET task_status = task_status_new");

        // Step 4: Remove staging column
        $this->dropColumn('task', 'task_status_new');
    }

    public function safeDown()
    {
        // Reverse mapping: Yii2 → Yii1 (approximate, destructive — some Yii1 codes cannot be recovered)
        $this->addColumn('task', 'task_status_old', $this->tinyInteger()->defaultValue(1));

        $this->execute("UPDATE task SET task_status_old = 5  WHERE task_status = 4"); // Completed
        $this->execute("UPDATE task SET task_status_old = 6  WHERE task_status = 5"); // Closed → Re-Assigned
        $this->execute("UPDATE task SET task_status_old = 3  WHERE task_status = 2"); // Started → On Going
        $this->execute("UPDATE task SET task_status_old = 2  WHERE task_status = 3"); // Stopped → Accepted
        $this->execute("UPDATE task SET task_status_old = 1  WHERE task_status = 1"); // Pending

        $this->execute("UPDATE task SET task_status = task_status_old");
        $this->dropColumn('task', 'task_status_old');
    }
}
