<?php

namespace backend\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search helper for the Client-Quotation index page.
 * Mirrors delivery's ClientQuotationSearch but uses the merged `client` table
 * instead of `client_details` (no `old_id` fallback needed).
 */
class ClientQuotationSearch extends Model
{
    public $client_id;
    public $company;
    public $quote_no;
    public $project_name;
    public $quoted_by;
    public $status;

    public function rules(): array
    {
        return [
            [['client_id', 'company', 'quote_no', 'project_name', 'quoted_by'], 'safe'],
            [['status'], 'integer'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = ClientQuotation::find()
            ->alias('q')
            ->joinWith(['client c'])
            ->where(['!=', 'q.status', 0])
            ->andWhere([
                'or',
                'q.id = q.parent_id',
                ['q.parent_id' => null],
                ['q.parent_id' => 0],
            ])
            ->orderBy(['q.id' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 10],
        ]);

        $this->load($params);

        if (!empty($this->client_id)) {
            $query->andWhere(['q.client_id' => $this->client_id]);
        }
        if (!empty($this->company)) {
            $query->andWhere(['like', 'c.company_name', $this->company]);
        }
        if (!empty($this->quote_no)) {
            $query->andWhere(['like', 'q.quote_no', $this->quote_no]);
        }
        if (!empty($this->project_name)) {
            $query->andWhere(['like', 'q.project_name', $this->project_name]);
        }
        if (!empty($this->quoted_by)) {
            $query->andWhere(['q.quoted_by' => $this->quoted_by]);
        }
        if ($this->status !== null && $this->status !== '') {
            $statusVal = (int) $this->status;
            if ($statusVal === 3) {
                // Completed = active row marked completed.
                $query->andWhere(['q.status' => 1, 'q.completed_status' => 1]);
            } elseif ($statusVal === 1) {
                // Active = active row not yet completed.
                $query->andWhere(['q.status' => 1])
                      ->andWhere(['or', ['q.completed_status' => 0], ['q.completed_status' => null]]);
            } else {
                $query->andWhere(['q.status' => $statusVal]);
            }
        }

        return $dataProvider;
    }
}
