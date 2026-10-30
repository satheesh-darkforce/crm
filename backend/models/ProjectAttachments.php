<?php
namespace backend\models;
use yii\db\ActiveRecord;
class ProjectAttachments extends ActiveRecord {
    public static function tableName(): string { return 'project_attachments'; }
    public function rules(): array { return [[['project_id'], 'required'], [['project_id', 'uploaded_by', 'status'], 'integer'], [['file_name', 'file_path'], 'string', 'max' => 500], [['created_at'], 'safe']]; }
    public function getProject(): \yii\db\ActiveQuery { return $this->hasOne(Project::class, ['id' => 'project_id']); }
}
