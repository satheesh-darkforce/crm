<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\DcDetail;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\web\NotFoundHttpException;

/**
 * Landing page for the Quotation / Delivery module.
 *
 * Ported from delivery's SiteController (actionHome / actionListDc / actionView).
 * The dashboard shows DC summary tiles, a client-lookup grid with "Add Quote"
 * and "Add DC" action buttons, and drill-down lists per DC type.
 *
 * Access control is automatic — base Controller enforces UserAccess::checkAccess.
 */
class QuotationDashboardController extends Controller
{
    /** Summary tiles + client list. */
    public function actionIndex(): string
    {
        $query = ClientModel::find()->where(['status' => 1]);

        // Exclude Inward DCs (type=2) — they have their own listing page.
        $dc = DcDetail::find()
            ->where(['status' => 1])
            ->andWhere(['or', ['type' => null], ['type' => 1]])
            ->asArray()->all();
        $cancelledDc = (int) DcDetail::find()
            ->where(['status' => 2])
            ->andWhere(['or', ['type' => null], ['type' => 1]])
            ->count();
        // Total DC includes cancelled rows so the dashboard tile matches "All DC" listing.
        $totalDc = count($dc) + $cancelledDc;

        $returnableDc = $saleDc = $serviceDc = $exceededDc = $returnedDc = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($dc as $row) {
            if ((int)$row['dc_type'] === 1) {
                $returnableDc++;
                $notReturned = empty($row['dc_returned_date']) || $row['dc_returned_date'] === '0000-00-00';
                if ($notReturned) {
                    if (!empty($row['dc_return_date']) && $row['dc_return_date'] < $now) {
                        $exceededDc++;
                    }
                } else {
                    $returnedDc++;
                }
            } elseif ((int)$row['dc_type'] === 2) {
                $saleDc++;
            } elseif ((int)$row['dc_type'] === 3) {
                $serviceDc++;
            }
        }

        $inwardDc = (int) DcDetail::find()
            ->where(['status' => 1, 'type' => 2])
            ->count();

        $summary = [
            'total_dc'      => $totalDc,
            'returnable_dc' => $returnableDc,
            'sale_dc'       => $saleDc,
            'service_dc'    => $serviceDc,
            'exceeded_dc'   => $exceededDc,
            'returned_dc'   => $returnedDc,
            'cancelled_dc'  => $cancelledDc,
            'inward_dc'     => $inwardDc,
        ];

        $modelOpen = 0;
        $company = $client = '';
        if (Yii::$app->request->isPost) {
            $action = Yii::$app->request->post('action');
            if ($action === 'submit') {
                $company = trim((string) Yii::$app->request->post('company_name', ''));
                $client  = trim((string) Yii::$app->request->post('client_name', ''));
                if ($company !== '') {
                    $query->andWhere(['like', 'company_name', $company . '%', false]);
                }
                if ($client !== '') {
                    $query->andWhere(['like', 'client_name', $client . '%', false]);
                }
                $modelOpen = 1;
            }
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 10],
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'modelOpen'    => $modelOpen,
            'companyName'  => $company,
            'clientName'   => $client,
            'dc'           => $summary,
        ]);
    }

    /** Filtered DC lists by type (alldc / returnable / sale / overdue / returned / cancelled). */
    public function actionListDc(): string
    {
        $type = (string) Yii::$app->request->get('type', 'alldc');
        $title = 'All DC';

        $query = DcDetail::find()
            ->alias('dc')
            ->where(['!=', 'dc.status', 0])
            ->andWhere(['or', ['dc.type' => null], ['dc.type' => 1]])
            ->joinWith('client client')
            ->with('delivery');

        switch ($type) {
            case 'returnable':
                $title = 'Returnable DC';
                $query->andWhere(['dc.dc_type' => 1]);
                break;
            case 'sale':
                $title = 'Sale DC';
                $query->andWhere(['dc.dc_type' => 2]);
                break;
            case 'service':
                $title = 'Service DC';
                $query->andWhere(['dc.dc_type' => 3]);
                break;
            case 'overdue':
                $title = 'Overdue DC';
                $query->andWhere(['dc.dc_type' => 1])
                      ->andWhere(['<', 'dc.dc_return_date', date('Y-m-d H:i:s')])
                      ->andWhere(['dc.dc_returned_date' => null]);
                break;
            case 'returned':
                $title = 'Returned DC';
                $query->andWhere(['dc.dc_type' => 1])
                      ->andWhere(['not', ['dc.dc_returned_date' => null]]);
                break;
            case 'cancelled':
                $title = 'Cancelled DC';
                $query = DcDetail::find()->alias('dc')->where(['dc.status' => 2])
                                 ->joinWith('client client')->with('delivery');
                break;
        }

        $dataProvider = new ArrayDataProvider([
            'allModels' => $query->orderBy(['dc.id' => SORT_DESC])->asArray()->all(),
            'pagination' => ['pageSize' => 10],
            'sort' => [
                'attributes' => ['dc_no', 'dc_date', 'dc_return_date'],
            ],
        ]);

        return $this->render('list-dc', [
            'dataProvider' => $dataProvider,
            'title' => $title,
            'type' => $type,
        ]);
    }

    /** Full-detail DC view (read-only). */
    public function actionViewDc(int $id): string
    {
        $dc = DcDetail::find()
            ->alias('t')
            ->where(['t.id' => $id])
            ->andWhere(['!=', 't.status', 0])
            ->with(['client', 'delivery', 'documents', 'dcProducts.product'])
            ->asArray()
            ->one();

        if (!$dc) {
            throw new NotFoundHttpException('Delivery challan not found.');
        }

        return $this->render('view-dc', ['model' => $dc]);
    }
}
