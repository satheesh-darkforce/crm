<?php

namespace backend\components;

use backend\models\Developer;
use backend\models\Employee;
use backend\models\Lead;
use backend\models\LeadFollowers;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * "Lead assigned" notification — port of Yii1 Utility::assignedMail().
 *
 * Sent to the assignee whenever a lead (its first follow-up) or a new
 * follow-up is assigned. Mail failures are logged, never thrown: a broken
 * SMTP setup must not block saving the lead.
 */
class LeadMailer
{
    public static function sendAssigned(Lead $lead, LeadFollowers $followUp): void
    {
        $assigned = (int)$followUp->assigned_to;
        if (!$assigned || $assigned === (int)Yii::$app->user->id) {
            return; // nothing to notify when you assign the lead to yourself
        }

        $employee  = Employee::findOne(['user_id' => $assigned]);
        $developer = $employee ? null : Developer::findOne(['user_id' => $assigned]);
        $email     = $employee->email_id ?? $developer->email_id ?? '';
        $name      = $employee->employee_name ?? $developer->developer_name ?? '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $rows = [
            'Client Name'    => $lead->client_name,
            'Contact No.'    => $lead->contact_no,
            'Email'          => $lead->email,
            'Contact Person' => $lead->contact_person_name,
            'Project'        => $lead->title,
            'Subject'        => $followUp->subject,
            'Follow Up'      => $followUp->getScheduledLabel(),
            'Mode'           => Lead::modeLabel($followUp->mode),
        ];
        $body = '<p>Hi ' . Html::encode($name) . ',</p><p>A lead has been assigned to you.</p><table cellpadding="4">';
        foreach ($rows as $label => $value) {
            $body .= '<tr><td><b>' . $label . '</b></td><td>:</td><td>' . Html::encode($value ?: '-') . '</td></tr>';
        }
        $body .= '<tr><td><b>Description</b></td><td>:</td><td>' . Lead::renderRichText($followUp->description) . '</td></tr></table>';
        $body .= '<p>' . Html::a('Open the lead', Url::to(['/lead/view', 'id' => $lead->id], true)) . '</p>';

        try {
            Yii::$app->mailer->compose()
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setTo($email)
                ->setSubject('New Lead Assigned to ' . $name)
                ->setHtmlBody($body)
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Lead assigned mail failed for lead #' . $lead->id . ': ' . $e->getMessage(), __METHOD__);
        }
    }
}
