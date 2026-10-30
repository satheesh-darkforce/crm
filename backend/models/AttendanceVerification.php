<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: AttendanceVerification model
 *
 * Table: attendanceverification
 *
 * @property int    $id
 * @property int    $developer_id
 * @property string $attendance_date
 * @property int    $total_late
 * @property string $late_lop
 * @property int    $total_leave
 * @property int    $cl_taken
 * @property int    $cl_backup
 * @property int    $total_absent
 * @property string $leave_lop
 * @property float  $total_lop
 * @property int    $verified
 * @property string $reason
 * @property string $created_date
 * @property int    $status
 */
class AttendanceVerification extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'attendanceverification';
    }

    public function rules(): array
    {
        return [
            [['developer_id', 'attendance_date', 'created_date'], 'required'],
            [['developer_id', 'total_late', 'total_leave', 'cl_taken', 'cl_backup', 'total_absent', 'verified', 'status'], 'integer'],
            [['total_lop'], 'number'],
            [['late_lop', 'leave_lop'], 'string', 'max' => 10],
            [['reason'], 'string'],
            [['attendance_date', 'created_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'              => 'ID',
            'developer_id'    => 'Employee',
            'attendance_date' => 'Attendance Date',
            'total_late'      => 'Total Late',
            'late_lop'        => 'Late LOP',
            'total_leave'     => 'Total Leave',
            'cl_taken'        => 'CL Taken',
            'cl_backup'       => 'CL Backup',
            'total_absent'    => 'Total Absent',
            'leave_lop'       => 'Leave LOP',
            'total_lop'       => 'Total LOP',
            'verified'        => 'Verified',
            'reason'          => 'Reason',
            'created_date'    => 'Created Date',
            'status'          => 'Status',
        ];
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }
}
