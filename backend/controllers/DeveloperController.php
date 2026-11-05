<?php

namespace backend\controllers;

use backend\components\PayrollCalculator;
use backend\models\CrmToken;
use backend\models\Developer;
use backend\models\SalaryAllowance;
use backend\models\UploadPayslip;
use backend\models\User;
use backend\models\WorkReport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\bootstrap5\ActiveForm;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Backend CRUD for Developer (HR — developer/employee portal users).
 */
class DeveloperController extends Controller
{
    /** Full pages get the HR module stylesheet and script. */
    public function render($view, $params = []): string
    {
        \backend\assets\HrAsset::register($this->getView());
        return parent::render($view, $params);
    }

    /** Routes that make someone an employee manager (explicit grants only, never defaults). */
    private const MANAGE_ROUTES = ['developer/index', 'developer/update', 'developer/create', 'developer/ex-employees'];

    public function actionIndex(): string
    {
        $query = Developer::find()->where(['status' => 1])->orderBy(['id' => SORT_ASC]);

        if ($v = Yii::$app->request->get('name'))        { $query->andWhere(['like', 'developer_name', $v]); }
        if ($v = Yii::$app->request->get('designation')) { $query->andWhere(['like', 'designation', $v]); }
        if ($v = Yii::$app->request->get('email'))       { $query->andWhere(['like', 'email_id', $v]); }
        if ($v = Yii::$app->request->get('mobile'))      { $query->andWhere(['like', 'mobile', $v]); }

        $dept = Yii::$app->request->get('department');
        if ($dept !== null && $dept !== '') { $query->andWhere(['department' => (int)$dept]); }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView(int $id): string
    {
        // developer/view is a developer default: their own profile only, unless they manage employees.
        \backend\components\HrAccess::ensureOwnOrManager($id, self::MANAGE_ROUTES, 'Employee');
        $model           = $this->findModel($id, false);
        $currentMonthAll = WorkReport::getByEmployee($id, 1);
        $lastMonthAll    = WorkReport::getByEmployee($id, 2);
        $allTimeAll      = WorkReport::getByEmployee($id);

        // Pagination — 10 rows per table on each section.
        $cmPagination = new Pagination([
            'totalCount' => count($currentMonthAll), 'pageSize' => 10,
            'pageParam'  => 'cm_page', 'params' => Yii::$app->request->queryParams,
        ]);
        $lmPagination = new Pagination([
            'totalCount' => count($lastMonthAll), 'pageSize' => 10,
            'pageParam'  => 'lm_page', 'params' => Yii::$app->request->queryParams,
        ]);
        $allTimePagination = new Pagination([
            'totalCount' => count($allTimeAll), 'pageSize' => 10,
            'pageParam'  => 'at_page', 'params' => Yii::$app->request->queryParams,
        ]);

        $currentMonth = array_slice($currentMonthAll, $cmPagination->offset, $cmPagination->limit);
        $lastMonth    = array_slice($lastMonthAll,    $lmPagination->offset, $lmPagination->limit);
        $allTime      = array_slice($allTimeAll,      $allTimePagination->offset, $allTimePagination->limit);

        // Footer totals reflect ALL rows, not just the current page.
        $cmTotal      = (int)array_sum(array_column($currentMonthAll, 'total_secs'));
        $lmTotal      = (int)array_sum(array_column($lastMonthAll,    'total_secs'));
        $allTimeTotal = (int)array_sum(array_column($allTimeAll,      'total_secs'));

        return $this->render('view', compact(
            'model',
            'currentMonth', 'cmPagination', 'cmTotal',
            'lastMonth',    'lmPagination', 'lmTotal',
            'allTime',      'allTimePagination', 'allTimeTotal'
        ));
    }

    public function actionCreate(): string|Response
    {
        $developer = new Developer();
        $user      = new User();

        // Suggest the next code in the series. Shown pre-filled and editable; the
        // value is resolved again at insert time so a stale form can't duplicate it.
        $suggestedEmpCode      = Developer::nextEmpCode();
        $developer->emp_code   = $suggestedEmpCode;

        // Handle AJAX field validation (triggered by enableAjaxValidation on the form)
        if (Yii::$app->request->isAjax && Yii::$app->request->isPost) {
            $developer->load(Yii::$app->request->post());
            $response         = Yii::$app->response;
            $response->format = Response::FORMAT_JSON;
            $response->data   = ActiveForm::validate($developer);
            return $response;
        }

        if (Yii::$app->request->isPost) {
            $post         = Yii::$app->request->post();
            $rawPassword  = trim($post['password'] ?? '');
            $repeatPwd    = trim($post['repeatpassword'] ?? '');

            if ($rawPassword === '') {
                Yii::$app->session->setFlash('error', 'Password is required.');
                return $this->render('create', compact('developer', 'user', 'suggestedEmpCode'));
            }
            if ($rawPassword !== $repeatPwd) {
                Yii::$app->session->setFlash('error', 'Passwords do not match.');
                return $this->render('create', compact('developer', 'user', 'suggestedEmpCode'));
            }

            $user->username = trim($post['username'] ?? '');
            $user->usertype = User::TYPE_DEVELOPER;
            $user->status   = 1;
            $user->setPassword($rawPassword);

            if (!$user->validate()) {
                Yii::$app->session->setFlash('error', implode(', ', $user->getFirstErrors()));
                return $this->render('create', compact('developer', 'user', 'suggestedEmpCode'));
            }
            if (!$user->save(false)) {
                Yii::$app->session->setFlash('error', 'Failed to create user account.');
                return $this->render('create', compact('developer', 'user', 'suggestedEmpCode'));
            }

            $developer->load($post, 'Developer');
            $developer->user_id = $user->user_id;
            $developer->status  = 1;

            // Emp code resolution. "Auto" = the field is blank, or still holds the
            // code we offered — i.e. the user did not choose it themselves.
            //  - auto   → re-resolved here, because the suggestion may have been
            //             taken while this form sat open
            //  - typed  → left as-is, so a clash surfaces through the unique
            //             validator instead of being silently overwritten
            $submittedEmpCode = trim((string)$developer->emp_code);
            $offeredEmpCode   = trim((string)($post['emp_code_auto'] ?? ''));
            $autoEmpCode      = $submittedEmpCode === '' || $submittedEmpCode === $offeredEmpCode;

            if ($autoEmpCode && ($submittedEmpCode === ''
                || Developer::find()->where(['emp_code' => $submittedEmpCode])->exists())) {
                $developer->emp_code = Developer::nextEmpCode();
            }

            $photo = UploadedFile::getInstance($developer, 'profile_photo');
            if ($photo) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($photo->extension), $allowed)) {
                    $user->delete();
                    Yii::$app->session->setFlash('error', 'Profile photo must be an image file (jpg, jpeg, png, gif, webp).');
                    return $this->render('create', compact('developer', 'user', 'suggestedEmpCode'));
                }
                $filename   = 'dev_' . $user->user_id . '_' . time() . '.' . $photo->extension;
                $uploadPath = Yii::getAlias('@backend/web/uploads/profileimages/');
                if (!is_dir($uploadPath)) { mkdir($uploadPath, 0775, true); }
                $photo->saveAs($uploadPath . $filename);
                $developer->profile_photo = $filename;
            }

            // Emp-code uniqueness is enforced in code (there is no DB unique index),
            // so the only remaining gap is another insert landing between the check
            // above and this save. If that happens on an auto-generated code, roll to
            // the next free one and retry; a user-chosen code still reports its clash.
            $saved = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                if ($developer->save()) { $saved = true; break; }

                $onlyEmpCodeClash = $developer->hasErrors('emp_code') && count($developer->getErrors()) === 1;
                if (!$autoEmpCode || !$onlyEmpCodeClash) { break; }

                $developer->clearErrors();
                $developer->emp_code = Developer::nextEmpCode();
            }

            if ($saved) {
                Yii::$app->session->setFlash('success', 'Employee created successfully.');
                return $this->redirect(['view', 'id' => $developer->id]);
            }

            $user->delete();
            Yii::$app->session->setFlash('error', 'Failed to save employee: ' . implode(', ', $developer->getFirstErrors()));
        }

        return $this->render('create', compact('developer', 'user', 'suggestedEmpCode'));
    }

    public function actionUpdate(int $id): string|Response
    {
        // Allow editing both active employees (status=1) and ex-employees (status=0).
        $developer = $this->findModel($id, false);
        $user      = $developer->user_id ? User::findOne(['user_id' => $developer->user_id]) : null;

        if (Yii::$app->request->isAjax && Yii::$app->request->isPost) {
            $developer->load(Yii::$app->request->post());
            $response         = Yii::$app->response;
            $response->format = Response::FORMAT_JSON;
            $response->data   = ActiveForm::validate($developer);
            return $response;
        }

        $existingPhoto = $developer->profile_photo;

        if ($developer->load(Yii::$app->request->post())) {
            // Optional password update (Yii1 resetpassword parity).
            // If left blank, the existing password is kept.
            $rawPassword = trim(Yii::$app->request->post('password', ''));
            $repeatPwd   = trim(Yii::$app->request->post('repeatpassword', ''));
            $changePassword = $rawPassword !== '' || $repeatPwd !== '';
            if ($changePassword) {
                if (!$user) {
                    Yii::$app->session->setFlash('error', 'Linked user account not found.');
                    return $this->render('update', compact('developer', 'user'));
                }
                if (strlen($rawPassword) < 6) {
                    Yii::$app->session->setFlash('error', 'Password must be at least 6 characters.');
                    return $this->render('update', compact('developer', 'user'));
                }
                if ($rawPassword !== $repeatPwd) {
                    Yii::$app->session->setFlash('error', 'Passwords do not match.');
                    return $this->render('update', compact('developer', 'user'));
                }
            }

            $photo = UploadedFile::getInstance($developer, 'profile_photo');
            if ($photo) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($photo->extension), $allowed)) {
                    Yii::$app->session->setFlash('error', 'Profile photo must be an image file (jpg, jpeg, png, gif, webp).');
                    $developer->profile_photo = $existingPhoto;
                    return $this->render('update', compact('developer', 'user'));
                }
                $filename   = 'dev_' . $developer->user_id . '_' . time() . '.' . $photo->extension;
                $uploadPath = Yii::getAlias('@backend/web/uploads/profileimages/');
                if (!is_dir($uploadPath)) { mkdir($uploadPath, 0775, true); }
                $photo->saveAs($uploadPath . $filename);
                $developer->profile_photo = $filename;
            } else {
                // No new file uploaded — keep the existing photo
                $developer->profile_photo = $existingPhoto;
            }

            if ($developer->save()) {
                if ($changePassword && $user) {
                    $user->setPassword($rawPassword);
                    $user->save(false);
                }
                Yii::$app->session->setFlash('success', 'Developer updated successfully.');
                // JS location.replace() instead of a 302 → drops /update out of
                // browser history so View ↔ Edit back-button loop can't happen.
                // See memory: history_loop_replace_pattern (three-leg fix).
                $viewUrl     = \yii\helpers\Url::to(['view', 'id' => $developer->id]);
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

        return $this->render('update', compact('developer', 'user'));
    }

    public function actionExEmployees(): string
    {
        $query = Developer::find()->where(['status' => 0])->orderBy(['id' => SORT_DESC]);

        if ($v = Yii::$app->request->get('name'))        { $query->andWhere(['like', 'developer_name', $v]); }
        if ($v = Yii::$app->request->get('designation')) { $query->andWhere(['like', 'designation', $v]); }
        if ($v = Yii::$app->request->get('email'))       { $query->andWhere(['like', 'email_id', $v]); }
        if ($v = Yii::$app->request->get('mobile'))      { $query->andWhere(['like', 'mobile', $v]); }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('ex-employees', ['dataProvider' => $dataProvider]);
    }

    public function actionResetPassword(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $developer = $this->findModel($id);
        $user      = User::findOne($developer->user_id);
        if (!$user) {
            throw new NotFoundHttpException('User account not found.');
        }
        $newPassword = trim(Yii::$app->request->post('new_password', ''));
        if (strlen($newPassword) < 6) {
            Yii::$app->session->setFlash('error', 'Password must be at least 6 characters.');
            return $this->redirect(['/employee/index']);
        }
        $user->setPassword($newPassword);
        $user->save(false);
        Yii::$app->session->setFlash('success', 'Password reset successfully.');
        return $this->redirect(['/employee/index']);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }

        $developer         = $this->findModel($id);
        $developer->status = 0;
        $developer->save(false);

        // Mirror Yii1 side-effects: mangle the linked user account and revoke tokens
        if ($developer->user_id) {
            $user = User::findOne($developer->user_id);
            if ($user) {
                $timestamp       = date('Y-m-d H:i:s A');
                $user->username  = $user->username . ' - deleted @' . $timestamp;
                $user->password  = $timestamp;
                $user->save(false);
            }
            CrmToken::deleteAll(['user_id' => $developer->user_id]);
        }

        Yii::$app->session->setFlash('success', 'Developer deactivated.');
        return $this->redirect(['index']);
    }

    /**
     * Main ERP route access permissions — mirrors Yii1 developer/accesspermissions/$id.
     * Stores comma-separated route strings in developer.access_permissions.
     */
    public function actionAccessPermissions(int $id): string|Response
    {
        $developer = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $selected = Yii::$app->request->post('accessoptions', []);
            $developer->access_permissions = implode(',', array_filter((array)$selected));
            $developer->save(false);
            Yii::$app->session->setFlash('success', 'Access permissions saved.');
            return $this->redirect(['access-permissions', 'id' => $developer->id]);
        }

        $stored = $developer->access_permissions
            ? array_map('trim', array_filter(explode(',', $developer->access_permissions)))
            : [];

        // Normalize: expand stored routes to include both Yii1 and Yii2 equivalents
        // so the view's in_array() works regardless of which format was saved.
        $routeMap    = \common\components\UserAccess::getYii2ToYii1RouteMap(); // yii2 => [yii1,...]
        $reverseMap  = [];  // yii1 => [yii2, ...]
        foreach ($routeMap as $yii2 => $yii1list) {
            foreach ($yii1list as $yii1) {
                $reverseMap[$yii1][] = $yii2;
            }
        }

        $permissions = $stored;
        foreach ($stored as $r) {
            // If it's a Yii2 route, add its Yii1 equivalents
            foreach ($routeMap[$r] ?? [] as $yii1) {
                $permissions[] = $yii1;
            }
            // If it's a Yii1 route, add its Yii2 equivalents
            foreach ($reverseMap[$r] ?? [] as $yii2) {
                $permissions[] = $yii2;
            }
        }
        $permissions = array_unique($permissions);

        return $this->render('access-permissions', [
            'developer'   => $developer,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Accounting portal (JSON) permissions — equivalent of Yii1 developer/accesspermissionsnew/$id.
     */
    public function actionAccessPermissionsNew(int $id): string|Response
    {
        $developer = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $overallArray  = json_decode(Yii::$app->request->post('overall_array', '[]'), true);
            $accessOptions = Yii::$app->request->post('accessoptions', []);
            $finalArray    = [];

            foreach ($overallArray as $group) {
                $mainMenu = $group['main_menu'];
                $finalArray[$mainMenu] = [];
                foreach ($group['menus'] as $item) {
                    $path = $item['menu_path'];
                    $finalArray[$mainMenu][$path] =
                        isset($accessOptions[$mainMenu]) && in_array($path, (array)$accessOptions[$mainMenu]) ? 1 : 0;
                }
            }

            $developer->access_permissions_new = json_encode($finalArray);
            $developer->save(false);
            Yii::$app->session->setFlash('success', 'Web portal permissions updated.');
            return $this->redirect(['view', 'id' => $developer->id]);
        }

        $permissions = [];
        if ($developer->access_permissions_new) {
            $decoded = json_decode($developer->access_permissions_new, true);
            if (is_array($decoded)) {
                foreach ($decoded as $mainMenu => $items) {
                    $permissions[$mainMenu] = array_keys(array_filter($items));
                }
            }
        }

        return $this->render('access-permissions-portal', ['developer' => $developer, 'permissions' => $permissions, 'field' => 'web']);
    }

    public function actionApkAccessPermissions(int $id): string|Response
    {
        $developer = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $overallArray  = json_decode(Yii::$app->request->post('overall_array', '[]'), true);
            $accessOptions = Yii::$app->request->post('accessoptions', []);
            $finalArray    = [];

            foreach ($overallArray as $group) {
                $mainMenu = $group['main_menu'];
                $finalArray[$mainMenu] = [];
                foreach ($group['menus'] as $item) {
                    $path = $item['menu_path'];
                    $finalArray[$mainMenu][$path] =
                        isset($accessOptions[$mainMenu]) && in_array($path, (array)$accessOptions[$mainMenu]) ? 1 : 0;
                }
            }

            $developer->apk_access_permissions = json_encode($finalArray);
            $developer->save(false);
            Yii::$app->session->setFlash('success', 'APK permissions updated.');
            return $this->redirect(['view', 'id' => $developer->id]);
        }

        $permissions = [];
        if ($developer->apk_access_permissions) {
            $decoded = json_decode($developer->apk_access_permissions, true);
            if (is_array($decoded)) {
                foreach ($decoded as $mainMenu => $items) {
                    $permissions[$mainMenu] = array_keys(array_filter($items));
                }
            }
        }

        return $this->render('access-permissions-portal', ['developer' => $developer, 'permissions' => $permissions, 'field' => 'apk']);
    }

    /**
     * Create Payslip listing (requirement #6). Lists only "salary applicable"
     * employees; the view lets the admin enter LOP / Extra Working Days and an
     * Incentive (editable only for incentive-applicable employees), then submits
     * to actionGeneratePayslip which emits the statutory CSV.
     */
    public function actionCreatePayslip(): string
    {
        // Payslip is generated for the PREVIOUS month; compute the period first so it
        // can also drive the "checked-in ex-employee" query below.
        $fromDate = date('Y-m-01', strtotime('first day of last month'));
        $toDate   = date('Y-m-t',  strtotime($fromDate));

        // Ex-employees (status=0) who actually checked in during the payslip month
        // still worked part of the month, so they must get a payslip too. "Checked in"
        // = at least one dailyattendance row in the period with a recorded in_time.
        $exDevIds = (new \yii\db\Query())
            ->select('developer_id')
            ->distinct()
            ->from('dailyattendance')
            ->where(['between', 'attendance_date', $fromDate, $toDate])
            ->andWhere(['not', ['in_time' => null]])
            ->column();

        // Active salary-applicable employees are always listed. Ex-employees are added
        // whenever they checked in during the payslip month, regardless of their
        // is_salary_applicable flag, since they worked part of the month.
        $developers = Developer::find()
            ->andWhere([
                'or',
                ['status' => 1, 'is_salary_applicable' => 1],
                ['and', ['status' => 0], ['id' => $exDevIds]],
            ])
            ->orderBy(['developer_name' => SORT_ASC])
            ->all();

        // Auto-calculate each employee's LOP for the PREVIOUS month (the period the
        // payslip is generated for) from attendance, using the same rules as the
        // Monthly Attendance grid and PayslipController so the figures always match.
        $lopMap   = [];
        foreach ($developers as $dev) {
            $lopMap[$dev->id] = \backend\components\LopCalculator::totalLop($dev->id, $fromDate, $toDate);
        }

        // Last time a payslip was generated for this month (upload_payslip drives the
        // Download button + the CSV/upload timestamp).
        $lastUpload = UploadPayslip::find()->where(['month_year' => $toDate])->one();

        // Unified "last generated" time: the most recent of the CSV/upload record
        // (upload_payslip.uploaded_date) and the stored payslips created by the cron
        // or the Generate button (payslip.created_date). So the page reflects whichever
        // ran last — cron, Create Payslip button, or upload.
        $storedGeneratedOn = (new \yii\db\Query())
            ->from('payslip')
            ->where(['status' => 1, 'month_year' => $fromDate])
            ->max('created_date');
        $lastGeneratedAt = null;
        foreach ([$lastUpload->uploaded_date ?? null, $storedGeneratedOn] as $t) {
            if (!empty($t) && ($lastGeneratedAt === null || strtotime($t) > strtotime($lastGeneratedAt))) {
                $lastGeneratedAt = $t;
            }
        }

        return $this->render('create-payslip', [
            'developers'      => $developers,
            'lopMap'          => $lopMap,
            'lastUpload'      => $lastUpload,
            'lastGeneratedAt' => $lastGeneratedAt,
            'monthParam'      => date('Y-m', strtotime($fromDate)),
        ]);
    }

    /**
     * Generate the payslip CSV (requirement #6).
     *
     * Salary breakdown (on PAY AFTER LOP): BASIC 50%, DA 12%, HRA 30%,
     * OTHERS = remainder. Allowance + Incentive are added only for applicable
     * employees; TDS / PT / PF / ESI deductions come from the employee's Salary
     * Management fields. The CSV matches "Payslip Details(March 2026).csv" plus
     * two extra columns (ALLOWANCE, INCENTIVE). The file is stored under
     * uploads/upload_payslip/ and registered in upload_payslip (re-generating a
     * month overwrites that month's record), then streamed to the browser.
     */
    public function actionGeneratePayslip(): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }

        $req       = Yii::$app->request;
        $monthRaw  = trim((string)$req->post('month_year', ''));
        if ($monthRaw === '') {
            Yii::$app->session->setFlash('error', 'Please select a month.');
            return $this->redirect(['create-payslip']);
        }
        // Normalise to the last day of the selected month (matches PayslipUpload).
        $monthYear = date('Y-m-t', strtotime($monthRaw . (strlen($monthRaw) === 7 ? '-01' : '')));

        $devIds      = $req->post('developer_id', []);
        $empCodes    = $req->post('emp_code', []);
        $names       = $req->post('name', []);
        $netSalaries = $req->post('net_salary', []);
        $lops        = $req->post('lop', []);
        $allowances  = $req->post('allowance', []);
        $incentives  = $req->post('incentive', []);
        $pts         = $req->post('pt', []);   // per-row editable Professional Tax

        $headers = [
            'S.No', 'EMP CODE', 'EMP NAME', 'TOTAL PAY', 'LOP', 'LOP (RS)', 'PAY AFTER LOP',
            'BASIC', 'DA', 'HRA', 'OTHERS', 'ALLOWANCE', 'INCENTIVE',
            'TDS', 'PT', 'PF', 'ESI', 'OTHER DEDUCTION', 'NET PAY',
            'EMPLOYER EPF CONTRIBUTION', 'EMPLOYER ESI CONTRIBUTION',
            'EMPLOYEE EPF CONTRIBUTION', 'EMPLOYEE ESI CONTRIBUTION', 'ACTION',
        ];

        $rows        = [];
        $userIds     = [];
        $totals      = array_fill(0, count($headers), 0); // running column totals (numeric cols only)
        $daysInMonth = (int)date('t', strtotime($monthYear)) ?: 30; // real calendar days for per-day rate
        // Central payroll config (PF/ESI rates + ceilings, earnings split, PT) — loaded once.
        $cfg = SalaryAllowance::activeByCode();

        foreach ($names as $i => $name) {
            $devId     = (int)($devIds[$i] ?? 0);
            $dev       = $devId ? Developer::findOne($devId) : null;

            $base      = (float)($netSalaries[$i] ?? 0);
            $lop       = (float)($lops[$i] ?? 0);

            // Earnings + statutory rates come from the central settings; whether PF/ESI/PT
            // apply is driven by each employee's Applicable flags (pf_status/esi_status/
            // professional_tax_status). ESI is still gated internally by its ceiling.
            $calc = $dev
                ? PayrollCalculator::forDeveloper($dev, $base, $daysInMonth, $lop, $cfg)
                : PayrollCalculator::compute($base, $daysInMonth, $lop, $cfg, false, false, false, false);

            $lopRs       = $calc['lopRs'];
            $payAfterLop = $calc['payAfterLop'];
            $basic       = $calc['basic'];
            $da          = $calc['da'];
            $hra         = $calc['hra'];
            $others      = $calc['others'];

            // Allowance editable on screen but only counted for allowance-applicable employees.
            $allowance = ($dev && (int)$dev->allowance_applicable === 1) ? round((float)($allowances[$i] ?? 0), 2) : 0.0;
            // Incentive editable on screen but only counted for incentive-applicable employees.
            $incentive = ($dev && (int)$dev->incentive_status === 1) ? round((float)($incentives[$i] ?? 0), 2) : 0.0;

            $tds = $calc['tds'];
            // PT is editable per row on the Create Payslip screen; use the posted value
            // (falls back to the engine's applicability-based default).
            $pt  = isset($pts[$i]) && is_numeric($pts[$i]) ? round((float)$pts[$i], 2) : $calc['pt'];
            // In this company BOTH the employee and employer parts of PF/ESI are debited
            // from the employee's salary, so the PF/ESI deduction column = sum of both.
            $pfEmployee  = $calc['employeePf'];
            $pfEmployer  = $calc['employerPf'];
            $esiEmployee = $calc['employeeEsi'];
            $esiEmployer = $calc['employerEsi'];
            $pf  = round($pfEmployee + $pfEmployer + $calc['pfAdmin'], 2); // total PF debited (incl. admin charge)
            $esi = round($esiEmployee + $esiEmployer, 2);                  // total ESI debited
            $otherDeduction = 0.0;

            // Engine's netPay already subtracted PF/ESI + TDS + its default PT. Swap in the
            // edited PT (back out the engine default, apply the posted one), then add
            // allowance + incentive.
            $netPay    = round($calc['netPay'] - $calc['pt'] + $pt + $allowance + $incentive, 2);

            // Employer/Employee statutory contributions (reported separately).
            $employerEpf = $pfEmployer;
            $employerEsi = $esiEmployer;
            $employeeEpf = $pfEmployee;
            $employeeEsi = $esiEmployee;

            $row = [
                $i + 1,
                $empCodes[$i] ?? ($dev->emp_code ?? ''),
                $name,
                round($base, 2),
                $lop,
                $lopRs,
                $payAfterLop,
                $basic, $da, $hra, $others, $allowance, $incentive,
                $tds, $pt, $pf, $esi, $otherDeduction, $netPay,
                $employerEpf, $employerEsi, $employeeEpf, $employeeEsi,
                '', // ACTION (blank, matches sample)
            ];
            $rows[] = $row;

            if ($dev && !empty($dev->user_id)) {
                $userIds[] = json_encode($dev->user_id);
            }

            // Accumulate numeric totals (skip S.No[0], EMP CODE[1], EMP NAME[2], ACTION[last]).
            foreach ($row as $ci => $val) {
                if ($ci >= 3 && $ci <= count($headers) - 2 && is_numeric($val)) {
                    $totals[$ci] += (float)$val;
                }
            }
        }

        // Total row (label in EMP NAME column, sums in numeric columns).
        $totalRow = array_fill(0, count($headers), '');
        $totalRow[2] = 'Total';
        for ($ci = 3; $ci <= count($headers) - 2; $ci++) {
            $totalRow[$ci] = round($totals[$ci], 2);
        }
        // LOP-count and S.No columns aren't meaningful sums beyond LOP days; keep LOP total.

        // ---- Build CSV content ---------------------------------------------------
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fputcsv($fh, $totalRow);
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        // ---- Save to uploads/upload_payslip/ (same place as PayslipUpload) -------
        $dir = Yii::getAlias('@backend/web/uploads/upload_payslip/');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $filename = $monthYear . '_' . date('Y-m-d-h-i-s-A') . '.csv';
        file_put_contents($dir . $filename, $content);
        $relPath = '/uploads/upload_payslip/' . $filename;

        // Upsert the upload_payslip record for this month (re-generate overwrites).
        $record = UploadPayslip::find()->where(['month_year' => $monthYear])->one() ?? new UploadPayslip();
        $record->month_year    = $monthYear;
        $record->file_path     = $relPath;
        $record->uploaded_by   = Yii::$app->user->id;
        $record->uploaded_date = date('Y-m-d H:i:s');
        $record->developer_id  = !empty($userIds) ? json_encode($userIds) : null;
        $record->save(false);

        // "Create" saves & records the timestamp, then returns to the page (which now
        // shows the new "Last updated" and an enabled Download button). Downloading is
        // a separate action so it never changes the last-updated time.
        Yii::$app->session->setFlash('success',
            'Payslip created for ' . date('M Y', strtotime($monthYear)) . '. Use Download to get the file.');
        return $this->redirect(['create-payslip']);
    }

    /**
     * Download the most recently created payslip CSV for a month (separate from
     * Create, so it does not change the "Last updated" timestamp).
     */
    public function actionDownloadPayslipFile(string $month): Response
    {
        $monthYear = date('Y-m-t', strtotime($month . (strlen($month) === 7 ? '-01' : '')));
        $record    = UploadPayslip::find()->where(['month_year' => $monthYear])->one();

        if (!$record || !$record->file_path) {
            Yii::$app->session->setFlash('error', 'No payslip has been created for this month yet.');
            return $this->redirect(['create-payslip']);
        }
        $path = Yii::getAlias('@backend/web') . $record->file_path;
        if (!is_file($path)) {
            Yii::$app->session->setFlash('error', 'Payslip file not found on the server.');
            return $this->redirect(['create-payslip']);
        }
        return Yii::$app->response->sendFile($path, 'Payslip_' . date('M-Y', strtotime($monthYear)) . '.csv');
    }

    /**
     * Inline-edit emp_code from the developer listing — mirrors Yii1
     * developer/updateempcode. Accepts POST {developerid, emp_code} and
     * persists the new code respecting the Developer model's unique rule.
     */
    public function actionUpdateEmpCode(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST only.']);
        }

        $developerId = (int) Yii::$app->request->post('developerid');
        $empCode     = trim((string) Yii::$app->request->post('emp_code', ''));

        if (!$developerId || $empCode === '') {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid input.']);
        }

        $developer = Developer::findOne(['id' => $developerId, 'status' => 1]);
        if (!$developer) {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid developer.']);
        }

        $developer->emp_code = $empCode;
        if (!$developer->validate(['emp_code'])) {
            return $this->asJson(['status' => 0, 'msg' => $developer->getFirstError('emp_code') ?: 'Unable to save.']);
        }
        $developer->save(false, ['emp_code']);
        return $this->asJson(['status' => 1, 'msg' => 'Employee Code updated successfully.']);
    }

    public function actionUpdateEmpPin(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST only.']);
        }

        $developerId = (int) Yii::$app->request->post('developer_id');
        $password    = Yii::$app->request->post('password', '');
        $pin         = Yii::$app->request->post('pin', '');
        $confirmPin  = Yii::$app->request->post('confirm_pin', '');

        if (!$developerId || $password === '' || $pin === '') {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid input.']);
        }
        if (!preg_match('/^\d{4}$/', $pin) || $pin !== $confirmPin) {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid PIN.']);
        }

        // Only your own PIN (admins / sub-admins may manage anyone's). Stops password guessing on others.
        if (!\backend\components\HrAccess::isManager() && !\backend\components\HrAccess::isOwn($developerId)) {
            return $this->asJson(['status' => 0, 'msg' => 'You can only change your own PIN.']);
        }
        $developer = Developer::findOne(['id' => $developerId, 'status' => 1]);
        if (!$developer) {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid user.']);
        }

        $user = User::findOne(['user_id' => $developer->user_id]);
        if (!$user || !$user->validatePassword($password)) {
            return $this->asJson(['status' => 0, 'msg' => 'Incorrect password.']);
        }

        $developer->emp_pin = $pin;
        $developer->save(false);

        return $this->asJson(['status' => 1, 'msg' => 'Employee pin updated successfully.']);
    }

    public function actionViewEmpPin(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST only.']);
        }

        $developerId  = (int) Yii::$app->request->post('developer_id');
        $viewPassword = Yii::$app->request->post('view_password', '');

        if (!$developerId || $viewPassword === '') {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid input.']);
        }

        // Only your own PIN (admins / sub-admins may manage anyone's). Stops password guessing on others.
        if (!\backend\components\HrAccess::isManager() && !\backend\components\HrAccess::isOwn($developerId)) {
            return $this->asJson(['status' => 0, 'msg' => 'You can only change your own PIN.']);
        }
        $developer = Developer::findOne(['id' => $developerId, 'status' => 1]);
        if (!$developer) {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid user.']);
        }

        $user = User::findOne(['user_id' => $developer->user_id]);
        if (!$user || !$user->validatePassword($viewPassword)) {
            return $this->asJson(['status' => 0, 'msg' => 'Incorrect password.']);
        }

        return $this->asJson(['status' => 1, 'msg' => 'Password Verified Successfully', 'pin' => $developer->emp_pin]);
    }

    public function actionExportEmployees(): Response
    {
        return $this->exportToExcel(
            Developer::find()->where(['status' => 1])->orderBy('developer_name')->all(),
            'Active-Employees'
        );
    }

    public function actionExportExEmployees(): Response
    {
        return $this->exportToExcel(
            Developer::find()->where(['status' => 0])->orderBy('developer_name')->all(),
            'Ex-Employees'
        );
    }

    private function exportToExcel(array $records, string $filename): Response
    {
        $headers = [
            'Name', 'Emp Code', 'Designation', 'Mobile', 'Email',
            'Address', 'Date of Joining', 'Date of Relieving',
            'Qualification', 'Bank', 'Bank Branch', 'IFSC', 'Account No',
            'Gross Salary', 'Net Salary',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $d) {
            $sheet->fromArray([
                $d->developer_name,
                $d->emp_code,
                $d->designation,
                $d->mobile,
                $d->email_id,
                $d->address,
                $d->doj ? date('d-m-Y', strtotime($d->doj)) : '',
                $d->dor ? date('d-m-Y', strtotime($d->dor)) : '',
                $d->qualification,
                $d->bank,
                $d->bank_branch,
                $d->ifsc_code,
                $d->account_number,
                $d->gross_salary,
                $d->net_salary,
            ], null, 'A' . $row++);
        }

        // Style header row
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return Yii::$app->response->sendContentAsFile(
            $content,
            $filename . '_' . date('Y-m-d') . '.xlsx',
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'inline' => false]
        );
    }

    private function findModel(int $id, bool $activeOnly = true): Developer
    {
        $condition = ['id' => $id];
        if ($activeOnly) {
            $condition['status'] = 1;
        }
        $model = Developer::findOne($condition);
        if (!$model) {
            throw new NotFoundHttpException('Developer not found.');
        }
        return $model;
    }
}
