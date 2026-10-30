<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Attendance model
 *
 * Table: attendance
 *
 * @property int    $id
 * @property int    $developer_id
 * @property string $in_time
 * @property string $in_ip
 * @property string $out_time
 * @property string $out_ip
 * @property float  $in_latitude
 * @property float  $in_longitude
 * @property float  $out_latitude
 * @property float  $out_longitude
 * @property int    $status
 */
class Attendance extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'attendance';
    }

    public function rules(): array
    {
        return [
            [['developer_id', 'status'], 'required'],
            [['developer_id', 'status'], 'integer'],
            [['in_latitude', 'in_longitude', 'out_latitude', 'out_longitude'], 'number'],
            [['in_time', 'out_time'], 'safe'],
            [['in_ip', 'out_ip'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'developer_id' => 'Developer',
            'in_time'      => 'Check In',
            'out_time'     => 'Check Out',
        ];
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }

    /**
     * Check attendance status for current developer (ported from Yii1).
     *
     * Returns:
     *  reply=0  not a developer
     *  reply=1  no attendance today (show check-in)
     *  reply=2  checked in but work report not submitted (show reminder)
     *  reply=3  checked in and work report done (show check-out)
     *  reply=4  last attendance date has no work report (fill that date first)
     */
    public static function check_attendance(): array
    {
        $reply    = 0;
        $msg      = '';
        $today    = date('Y-m-d');
        $lastDate = $today;

        $user = \Yii::$app->user->identity ?? null;
        if (!$user) {
            return ['reply' => $reply, 'msg' => $msg, 'date' => $lastDate];
        }

        // Get developer_id via the developer relation (works for any user type)
        $developerId = $user->getDeveloperId();
        if (!$developerId) {
            return ['reply' => $reply, 'msg' => $msg, 'date' => $lastDate];
        }

        $reply = 1;

        $todayAttendance = static::find()
            ->where(['status' => 1, 'developer_id' => $developerId])
            ->andWhere('DATE(in_time) = :today', [':today' => $today])
            ->one();

        if ($todayAttendance) {
            $reply = 2;
            $msg   = 'Please update your today work report before you check out';

            $hasWr = WorkReport::find()
                ->where(['status' => 1, 'developer_id' => $developerId])
                ->andWhere(['date' => $today])
                ->exists();

            if ($hasWr) {
                $reply = 3;
                $msg   = (string)$todayAttendance->id;
            }
        } else {
            $lastAttendance = static::find()
                ->where(['status' => 1, 'developer_id' => $developerId])
                ->orderBy(['in_time' => SORT_DESC])
                ->limit(1)
                ->one();

            if ($lastAttendance && $lastAttendance->in_time) {
                $lastAttDate = date('Y-m-d', strtotime($lastAttendance->in_time));

                $hasWr = WorkReport::find()
                    ->where(['status' => 1, 'developer_id' => $developerId])
                    ->andWhere(['date' => $lastAttDate])
                    ->exists();

                if (!$hasWr) {
                    $reply    = 4;
                    $msg      = 'Please update your ' . $lastAttDate . ' work report before you check in';
                    $lastDate = $lastAttDate;
                }
            }
        }

        return ['reply' => $reply, 'msg' => $msg, 'date' => $lastDate];
    }
}
