<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\Employee;
use backend\models\User;
use Yii;
use yii\data\Pagination;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for Employee (admin/sub_admin staff records).
 */
class EmployeeController extends Controller
{
    public function actionIndex(): string
    {
        $req         = Yii::$app->request;
        $name        = $req->get('name');
        $designation = $req->get('designation');
        $email       = $req->get('email');
        $mobile      = $req->get('mobile');

        // Build combined list: normalise both tables into plain arrays
        $empQuery = Employee::find()->where(['status' => 1])->orderBy(['employee_name' => SORT_ASC]);
        if ($name)        { $empQuery->andWhere(['like', 'employee_name', $name]); }
        if ($designation) { $empQuery->andWhere(['like', 'designation', $designation]); }
        if ($email)       { $empQuery->andWhere(['like', 'email_id', $email]); }
        if ($mobile)      { $empQuery->andWhere(['like', 'mobile', $mobile]); }

        $devQuery = Developer::find()->where(['status' => 1])->orderBy(['developer_name' => SORT_ASC]);
        if ($name)        { $devQuery->andWhere(['like', 'developer_name', $name]); }
        if ($designation) { $devQuery->andWhere(['like', 'designation', $designation]); }
        if ($email)       { $devQuery->andWhere(['like', 'email_id', $email]); }
        if ($mobile)      { $devQuery->andWhere(['like', 'mobile', $mobile]); }

        $allEmployees = [];
        foreach ($empQuery->all() as $emp) {
            $allEmployees[] = [
                'id'             => $emp->id,
                'name'           => $emp->employee_name,
                'designation'    => $emp->designation ?? '',
                'email'          => $emp->email_id ?? '',
                'mobile'         => $emp->mobile ?? '',
                'photo'          => null,
                'type'           => 'employee',
                'user_id'        => $emp->user_id,
                'view_url'       => ['view', 'id' => $emp->id],
                'edit_url'       => ['update', 'id' => $emp->id],
                'permission_url' => ['permission', 'id' => $emp->id],
                'delete_url'     => ['delete', 'id' => $emp->id],
                'delete_label'   => $emp->employee_name,
            ];
        }
        foreach ($devQuery->all() as $dev) {
            $allEmployees[] = [
                'id'             => $dev->id,
                'name'           => $dev->developer_name,
                'designation'    => $dev->designation ?? '',
                'email'          => $dev->email_id ?? '',
                'mobile'         => $dev->mobile ?? '',
                'photo'          => $dev->profile_photo ?? null,
                'type'           => 'developer',
                'user_id'        => $dev->user_id,
                'view_url'       => ['/developer/view', 'id' => $dev->id],
                'edit_url'       => ['/developer/update', 'id' => $dev->id],
                'permission_url' => null,
                'delete_url'     => ['/developer/delete', 'id' => $dev->id],
                'delete_label'   => $dev->developer_name,
            ];
        }

        // Sort combined list by name
        usort($allEmployees, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $totalCount = count($allEmployees);
        $pagination = new Pagination([
            'totalCount' => $totalCount,
            'pageSize'   => 15,
            'params'     => $req->queryParams,
        ]);

        $staff = array_slice($allEmployees, $pagination->offset, $pagination->limit);

        return $this->render('index', compact('staff', 'pagination'));
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        return $this->render('view', ['model' => $model]);
    }

    public function actionCreate(): string|Response
    {
        $employee = new Employee();
        $user     = new User();

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            $user->username = trim($post['username'] ?? '');
            $user->usertype = User::TYPE_SUBADMIN;
            $user->status   = 1;
            $rawPassword    = trim($post['password'] ?? '');
            $repeatPassword = trim($post['repeat_password'] ?? '');

            $employee->load($post, 'Employee');
            $employee->created_at   = date('Y-m-d H:i:s');
            $employee->updated_at   = date('Y-m-d H:i:s');
            $employee->status       = 1;
            $employee->access_menus = '';

            // Collect all errors first, then display together
            $hasUserError = false;

            if ($rawPassword === '') {
                $user->addError('password', 'Password is required.');
                $hasUserError = true;
            }
            if ($rawPassword !== '' && $repeatPassword !== $rawPassword) {
                $user->addError('password', 'Passwords do not match.');
                $hasUserError = true;
            }

            if (!$hasUserError) {
                $user->setPassword($rawPassword);
            }

            $userValid     = !$hasUserError && $user->validate();
            $employeeValid = $employee->validate();

            if (!$userValid || !$employeeValid) {
                return $this->render('create', compact('employee', 'user'));
            }

            // Wrap the user + employee insert in a single DB transaction so a
            // late uniqueness collision (race between validate() and save()) or
            // any other integrity violation rolls back the partial user row
            // instead of leaving an orphan. Catch SQLSTATE 23000 explicitly
            // and surface a friendly per-field error.
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if (!$user->save(false)) {
                    throw new \RuntimeException('Failed to create user account.');
                }

                $employee->user_id = $user->user_id;

                if (!$employee->save(false)) {
                    throw new \RuntimeException('Failed to save employee record.');
                }

                $transaction->commit();
                Yii::$app->session->setFlash('success', 'Employee created successfully.');
                return $this->redirect(['view', 'id' => $employee->id]);
            } catch (\yii\db\IntegrityException $e) {
                $transaction->rollBack();
                $msg = $e->getMessage();
                if (stripos($msg, 'username') !== false) {
                    $user->addError('username', 'This username is already taken.');
                } elseif (stripos($msg, 'mobile') !== false) {
                    $employee->addError('mobile', 'This mobile number is already used by another employee.');
                } elseif (stripos($msg, 'email') !== false) {
                    $employee->addError('email_id', 'This email is already used by another employee.');
                } else {
                    Yii::$app->session->setFlash('error', 'Could not create employee due to a duplicate value. Please verify username, mobile and email.');
                }
                Yii::error('Employee create integrity error: ' . $msg, __METHOD__);
            } catch (\Throwable $e) {
                $transaction->rollBack();
                Yii::error('Employee create failed: ' . $e->getMessage(), __METHOD__);
                Yii::$app->session->setFlash('error', 'Could not create employee. Please try again.');
            }
        }

        return $this->render('create', compact('employee', 'user'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $employee = $this->findModel($id);
        $user     = $employee->user_id ? User::findOne(['user_id' => $employee->user_id]) : null;

        if ($employee->load(Yii::$app->request->post())) {
            $rawPassword    = trim(Yii::$app->request->post('password', ''));
            $repeatPassword = trim(Yii::$app->request->post('repeat_password', ''));

            // Password is optional on edit. If left blank, keep current password.
            // If provided, validate and update via sha1 (matches Yii1 resetpassword flow).
            $changePassword = $rawPassword !== '' || $repeatPassword !== '';
            if ($changePassword) {
                if (!$user) {
                    Yii::$app->session->setFlash('error', 'Linked user account not found.');
                    return $this->render('update', compact('employee', 'user'));
                }
                if (strlen($rawPassword) < 6) {
                    $user->addError('password', 'Password must be at least 6 characters.');
                } elseif ($rawPassword !== $repeatPassword) {
                    $user->addError('password', 'Passwords do not match.');
                }
                if ($user->hasErrors()) {
                    return $this->render('update', compact('employee', 'user'));
                }
            }

            $employee->updated_at = date('Y-m-d H:i:s');
            if ($employee->save()) {
                if ($changePassword && $user) {
                    $user->setPassword($rawPassword);
                    $user->save(false);
                }
                Yii::$app->session->setFlash('success', 'Employee updated successfully.');
                // JS location.replace() instead of a 302 → drops /update out of
                // browser history so View ↔ Edit back-button loop can't happen.
                // (Same trick as ClientQuotationController::actionAddPo.)
                $viewUrl     = \yii\helpers\Url::to(['view', 'id' => $employee->id]);
                $viewUrlJson = \yii\helpers\Json::encode($viewUrl);
                $response    = Yii::$app->response;
                $response->format = Response::FORMAT_RAW;
                $response->headers->set('Content-Type', 'text/html; charset=utf-8');
                $response->content = "<!doctype html><meta charset=\"utf-8\"><title>Redirecting…</title>"
                    . "<script>window.location.replace({$viewUrlJson});</script>"
                    . "<noscript><meta http-equiv=\"refresh\" content=\"0;url={$viewUrl}\">"
                    . "<a href=\"{$viewUrl}\">Continue</a></noscript>";
                return $response;
            }
        }

        return $this->render('update', compact('employee', 'user'));
    }

    public function actionPermission(int $id): string|Response
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $menus = Yii::$app->request->post('access_menus', []);
            $model->access_menus = serialize($menus);
            $model->updated_at   = date('Y-m-d H:i:s');
            $model->save(false);
            Yii::$app->session->setFlash('success', 'Permissions updated successfully.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('permissions', ['model' => $model]);
    }

    public function actionResetPassword(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }
        $employee = $this->findModel($id);
        $user     = User::findOne($employee->user_id);
        if (!$user) {
            throw new NotFoundHttpException('User account not found.');
        }
        $newPassword = trim(Yii::$app->request->post('new_password', ''));
        if (strlen($newPassword) < 6) {
            Yii::$app->session->setFlash('error', 'Password must be at least 6 characters.');
            return $this->redirect(['view', 'id' => $id]);
        }
        $user->setPassword($newPassword);
        $user->save(false);
        Yii::$app->session->setFlash('success', 'Password reset successfully.');
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $employee         = $this->findModel($id);
        $employee->status = 0;
        $employee->save(false);

        // Deactivate the linked User account — matches Yii1
        if ($employee->user_id) {
            $user = User::findOne(['user_id' => $employee->user_id]);
            if ($user) {
                $user->status = 0;
                $user->save(false);
            }
        }

        Yii::$app->session->setFlash('success', 'Employee deactivated.');
        return $this->redirect(['index']);
    }

    private function findModel(int $id): Employee
    {
        $model = Employee::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Employee not found.');
        }
        return $model;
    }
}
