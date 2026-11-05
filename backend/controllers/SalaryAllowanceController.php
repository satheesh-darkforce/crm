<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\DeveloperAllowanceDeduction;
use backend\models\SalaryAllowance;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SalaryAllowanceController extends Controller
{
    /** Full pages get the HR module stylesheet and script. */
    public function render($view, $params = []): string
    {
        \backend\assets\HrAsset::register($this->getView());
        return parent::render($view, $params);
    }

    /** Redirect index → deductions (Manage Allowances screen has been retired). */
    public function actionIndex(): Response
    {
        return $this->redirect(['deductions']);
    }

    /** List allowances (type=1) */
    public function actionAllowances(): string
    {
        $search = Yii::$app->request->get('title');
        $query  = SalaryAllowance::find()->where(['type' => 1, 'status' => 1]);
        if ($search) {
            $query->andWhere(['like', 'title', $search]);
        }
        $dataProvider = new ActiveDataProvider([
            'query'      => $query->orderBy('title'),
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('allowances', compact('dataProvider', 'search'));
    }


    /** List deductions (type=2) */
    public function actionDeductions(): string
    {
        $search = Yii::$app->request->get('title');
        $type2  = Yii::$app->request->get('type2');
        // TDS is hidden from the Manage Deductions screen for now (excluded by its code).
        $query  = SalaryAllowance::find()
            ->where(['type' => 2, 'status' => 1])
            ->andWhere(['or', ['code' => null], ['<>', 'code', SalaryAllowance::CODE_TDS]]);
        if ($search) {
            $query->andWhere(['like', 'title', $search]);
        }
        if ($type2 !== null && $type2 !== '') {
            $query->andWhere(['type2' => (int)$type2]);
        }
        $dataProvider = new ActiveDataProvider([
            'query'      => $query->orderBy('title'),
            'pagination' => ['pageSize' => 50],
        ]);
        return $this->render('deductions', compact('dataProvider', 'search', 'type2'));
    }

    public function actionCreateAllowance(): string|Response
    {
        $model = new SalaryAllowance();
        $model->type   = 1;
        $model->status = 1;

        if ($model->load(Yii::$app->request->post())) {
            $model->type = 1;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Allowance added.');
                return $this->redirect(['allowances']);
            }
        }

        return $this->render('_form', compact('model'));
    }

    public function actionCreateDeduction(): string|Response
    {
        $model = new SalaryAllowance();
        $model->type   = 2;
        $model->status = 1;

        if ($model->load(Yii::$app->request->post())) {
            $model->type = 2;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Deduction added.');
                return $this->redirect(['deductions']);
            }
        }

        return $this->render('_form', compact('model'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);
        $originalType = $model->type;

        if ($model->load(Yii::$app->request->post())) {
            $model->type = $originalType;
            // Guard: editing one earnings-split row must keep Basic+DA+HRA+Others = 100%.
            $err = $this->earningsSplitError($model);
            if ($err !== null) {
                Yii::$app->session->setFlash('error', $err);
            } elseif ($model->save()) {
                Yii::$app->session->setFlash('success', $originalType == 1 ? 'Allowance updated.' : 'Deduction updated.');
                return $this->redirect($originalType == 1 ? ['allowances'] : ['deductions']);
            }
        }

        return $this->render('_form', compact('model'));
    }

    /** The four codes whose percentages must always total 100%. */
    private const EARNINGS_CODES = [
        SalaryAllowance::CODE_BASIC,
        SalaryAllowance::CODE_DA,
        SalaryAllowance::CODE_HRA,
        SalaryAllowance::CODE_OTHERS,
    ];

    /**
     * If $model is an earnings-split row, return an error string when the four
     * percentages (with $model's new value substituted) don't total 100%.
     */
    private function earningsSplitError(SalaryAllowance $model): ?string
    {
        if (!in_array($model->code, self::EARNINGS_CODES, true)) {
            return null;
        }
        $total = (float)$model->default_value;
        foreach (self::EARNINGS_CODES as $c) {
            if ($c === $model->code) { continue; }
            $other = SalaryAllowance::findByCode($c);
            $total += $other ? (float)$other->default_value : 0;
        }
        if (round($total, 2) != 100.0) {
            return 'Basic + DA + HRA + Others must total 100%. This change would make it '
                . rtrim(rtrim(number_format($total, 2), '0'), '.') . '%.';
        }
        return null;
    }

    /**
     * Edit the earnings split (Basic / DA / HRA / Others) in one screen, with a
     * hard rule that the four percentages total exactly 100%.
     */
    public function actionEarningsSplit(): string|Response
    {
        $rows = [];
        foreach (self::EARNINGS_CODES as $c) {
            $m = SalaryAllowance::findByCode($c);
            if ($m) { $rows[$c] = $m; }
        }

        if (Yii::$app->request->isPost) {
            $vals  = Yii::$app->request->post('split', []);
            $total = 0.0;
            $clean = [];
            foreach (self::EARNINGS_CODES as $c) {
                $v = isset($vals[$c]) && is_numeric($vals[$c]) ? (float)$vals[$c] : 0.0;
                if ($v < 0 || $v > 100) {
                    Yii::$app->session->setFlash('error', 'Each percentage must be between 0 and 100.');
                    return $this->render('earnings-split', compact('rows'));
                }
                $clean[$c] = $v;
                $total    += $v;
            }
            if (round($total, 2) != 100.0) {
                Yii::$app->session->setFlash('error', 'Basic + DA + HRA + Others must total exactly 100%. Current total: '
                    . rtrim(rtrim(number_format($total, 2), '0'), '.') . '%.');
            } else {
                foreach ($rows as $c => $m) {
                    $m->default_value = $clean[$c];
                    $m->save(false);
                }
                Yii::$app->session->setFlash('success', 'Earnings split updated.');
                return $this->redirect(['earnings-split']);
            }
        }

        return $this->render('earnings-split', compact('rows'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model = $this->findModel($id);
        $type  = $model->type;
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Deleted.');
        return $this->redirect($type == 1 ? ['allowances'] : ['deductions']);
    }

    /**
     * Per-developer allowance & deduction overrides.
     */
    public function actionDeveloper(int $id): string|Response
    {
        $developer = Developer::findOne(['id' => $id, 'status' => 1]);
        if (!$developer) {
            throw new NotFoundHttpException('Developer not found.');
        }

        $allComponents = SalaryAllowance::find()->where(['status' => 1])->orderBy(['type' => SORT_ASC, 'title' => SORT_ASC])->all();

        if (Yii::$app->request->isPost) {
            // Soft-delete existing active overrides, then re-insert
            DeveloperAllowanceDeduction::updateAll(
                ['status' => 0],
                ['developer_id' => $id, 'status' => 1]
            );
            $rows       = Yii::$app->request->post('components', []);
            $grossTotal = 0;
            foreach ($rows as $compId => $val) {
                $v = trim((string)$val);
                if ($v === '' || (float)$v == 0) continue;
                $sad = SalaryAllowance::findOne((int)$compId);
                $rec = new DeveloperAllowanceDeduction();
                $rec->developer_id           = $id;
                $rec->allowance_deduction_id = (int)$compId;
                $rec->value                  = (float)$v;
                $rec->status                 = 1;
                $rec->save(false);
                // Sum allowances (type=1) to calculate gross salary
                if ($sad && $sad->type == 1) {
                    $grossTotal += (float)$v;
                }
            }
            // Update developer's gross_salary
            $developer->gross_salary = $grossTotal;
            $developer->save(false);

            Yii::$app->session->setFlash('success', 'Salary components updated successfully.');
            return $this->redirect(['developer', 'id' => $id]);
        }

        $overrides = DeveloperAllowanceDeduction::find()
            ->where(['developer_id' => $id, 'status' => 1])
            ->indexBy('allowance_deduction_id')
            ->all();

        $historyProvider = new \yii\data\ActiveDataProvider([
            'query' => DeveloperAllowanceDeduction::find()
                ->with('allowanceDeduction')
                ->where(['developer_id' => $id, 'status' => 0])
                ->orderBy(['id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('developer', compact('developer', 'allComponents', 'overrides', 'historyProvider'));
    }

    private function findModel(int $id): SalaryAllowance
    {
        $model = SalaryAllowance::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Record not found.');
        }
        return $model;
    }
}
