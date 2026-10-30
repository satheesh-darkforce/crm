<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: CronJobCallLogs.
 * Table: cron_job_call_logs
 *
 * @property int    $id
 * @property string $url
 * @property string $date_time
 * @property string $response
 */
class CronJobCallLogs extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'cron_job_call_logs';
    }

    public function rules(): array
    {
        return [
            [['url'], 'string', 'max' => 2500],
            [['response'], 'string'],
            [['date_time'], 'safe'],
        ];
    }
}
