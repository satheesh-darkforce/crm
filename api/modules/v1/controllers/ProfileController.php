<?php

namespace api\modules\v1\controllers;

use common\models\User;
use Yii;

/**
 * Profile endpoints — migrated from Yii1 WebserviceController:
 *   actionGetmyprofile()  → GET  /api/v1/profile/me
 *   actionChangepassword() → POST /api/v1/profile/change-password
 */
class ProfileController extends BaseController
{
    /**
     * GET /api/v1/profile/me
     * Returns the developer profile for the authenticated user.
     */
    public function actionMe(): array
    {
        $developer = $this->getLoginDeveloper();

        if (!$developer) {
            return $this->error('Profile not found', 0);
        }

        return $this->success([
            'developer_id'  => $developer->id,
            'developer_name'=> $developer->developer_name,
            'designation'   => $developer->designation,
            'address'       => $developer->address,
            'mobile'        => $developer->mobile,
            'email_id'      => $developer->email_id,
            'qualification' => $developer->qualification,
            'profile_photo' => $developer->profile_photo ?? '',
        ]);
    }

    /**
     * POST /api/v1/profile/change-password
     * Fields: currentpassword, newpassword, confirmpassword
     */
    public function actionChangePassword(): array
    {
        $post = Yii::$app->request->post();

        $currentPassword = trim($post['currentpassword'] ?? '');
        $newPassword     = trim($post['newpassword'] ?? '');
        $confirmPassword = trim($post['confirmpassword'] ?? '');

        if ($currentPassword === '') {
            return $this->error('Current Password is empty');
        }
        if ($newPassword === '') {
            return $this->error('New Password is empty');
        }
        if ($confirmPassword === '') {
            return $this->error('Confirm Password is empty');
        }
        if ($newPassword !== $confirmPassword) {
            return $this->error('New Password and Confirm Password do not match');
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        if (!$user->validatePassword($currentPassword)) {
            return $this->error('Current Password is wrong');
        }

        $user->setPassword($newPassword);
        if ($user->save(false)) {
            return $this->success([], 'Password updated successfully');
        }

        return $this->error('Failed to update password. Try again.');
    }
}
