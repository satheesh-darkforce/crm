<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: DailyAttendance model
 *
 * Table: dailyattendance
 *
 * Leave type codes:
 *   1 = Present
 *   2 = Present (half day)
 *   3 = Absent (LOP)
 *   4 = Holiday
 *   5 = Late
 *   6 = Permission
 *   7 = Leave (CL)
 *   8 = Half Day Leave
 *
 * @property int    $id
 * @property int    $developer_id
 * @property string $attendance_date
 * @property string $in_time
 * @property string $out_time
 * @property int    $is_present
 * @property int    $is_permission
 * @property int    $cl_credited
 * @property int    $lop_exempt
 * @property int    $morning_lop_exempt
 * @property int    $evening_lop_exempt
 * @property int    $morning_office_work
 * @property int    $evening_office_work
 * @property int    $morning_late
 * @property int    $evening_late
 * @property int    $morning_leave
 * @property int    $evening_leave
 * @property int    $leave_type
 * @property string $leave_lop
 * @property string $morning_late_lop
 * @property string $evening_late_lop
 * @property string $morning_leave_lop
 * @property string $evening_leave_lop
 * @property string $total_lop
 * @property int    $is_holiday
 * @property int    $cl_debited
 * @property string $remarks
 * @property string $remarks_attachment
 * @property string $created_date
 * @property int    $status
 */
class DailyAttendance extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'dailyattendance';
    }

    public function rules(): array
    {
        return [
            [['developer_id', 'attendance_date'], 'required'],
            [['developer_id', 'is_present', 'is_permission', 'morning_late', 'evening_late', 'morning_leave',
              'evening_leave', 'leave_type', 'is_holiday', 'cl_debited', 'cl_credited', 'lop_exempt',
              'morning_lop_exempt', 'evening_lop_exempt', 'morning_office_work', 'evening_office_work', 'status'], 'integer'],
            [['leave_lop', 'morning_late_lop', 'evening_late_lop', 'morning_leave_lop', 'evening_leave_lop', 'total_lop'], 'string', 'max' => 10],
            [['remarks'], 'string'],
            [['remarks_attachment'], 'string', 'max' => 500],
            [['attendance_date', 'in_time', 'out_time', 'created_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'              => 'ID',
            'developer_id'    => 'Employee',
            'attendance_date' => 'Date',
            'in_time'         => 'In Time',
            'out_time'        => 'Out Time',
            'is_present'      => 'Present',
            'is_permission'   => 'Permission',
            'morning_late'    => 'Morning Late',
            'evening_late'    => 'Evening Late',
            'morning_leave'   => 'Morning Leave',
            'evening_leave'   => 'Evening Leave',
            'leave_type'      => 'Leave Type',
            'leave_lop'       => 'Leave LOP',
            'total_lop'       => 'Total LOP',
            'is_holiday'      => 'Holiday',
            'cl_debited'      => 'CL Debited',
            'remarks'         => 'Remarks',
            'remarks_attachment' => 'Attachment',
            'created_date'    => 'Created Date',
            'status'          => 'Status',
        ];
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }
}
