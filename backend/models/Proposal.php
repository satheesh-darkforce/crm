<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Table: proposals
 *
 * proposal_status: 1=Created, 2=Approved, 3=Canceled, 4=Updated Proposal,
 *                  5=Project Initiated, 6=Project Completed, 7=Project Dropped
 *
 * @property int    $id
 * @property string $title
 * @property int    $client_id
 * @property int    $project_id
 * @property string $attachment
 * @property int    $created_by
 * @property string $created_date
 * @property int    $status
 * @property int    $proposal_status
 */
class Proposal extends ActiveRecord
{
    /** @var UploadedFile|null */
    public $attachmentFile;

    public static function tableName(): string
    {
        return 'proposals';
    }

    public function rules(): array
    {
        return [
            [['title', 'client_id', 'project_id'], 'required'],
            [['client_id', 'project_id', 'created_by', 'status', 'proposal_status'], 'integer'],
            [['title', 'attachment'], 'string', 'max' => 255],
            [['created_date'], 'safe'],
            [['attachmentFile'], 'file', 'skipOnEmpty' => true,
                'extensions' => 'pdf,doc,docx,ppt,pptx',
                'maxSize' => 20 * 1024 * 1024],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'title'           => 'Title',
            'client_id'       => 'Client',
            'project_id'      => 'Project',
            'attachment'      => 'Attachment',
            'proposal_status' => 'Proposal Status',
            'created_date'    => 'Created Date',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            1 => 'Created',
            2 => 'Approved',
            3 => 'Canceled',
            4 => 'Updated Proposal',
            5 => 'Project Initiated',
            6 => 'Project Completed',
            7 => 'Project Dropped',
        ];
    }

    public function getStatusLabel(): string
    {
        return self::statusLabels()[$this->proposal_status] ?? 'Unknown';
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getProject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }
}
