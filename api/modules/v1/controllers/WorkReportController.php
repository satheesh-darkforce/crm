<?php

namespace api\modules\v1\controllers;

use common\models\Project;
use common\models\WorkReport;
use Yii;

/**
 * Work Report API endpoints — migrated from Yii1 WebserviceController:
 *   actionAddworkreport()    → POST /api/v1/work-report/add
 *   actionMyworkreports()    → POST /api/v1/work-report/my-list
 *   actionGetworkreport()    → POST /api/v1/work-report/view
 *   actionUpdateworkreport() → POST /api/v1/work-report/update
 *
 * Note: total_time is stored in seconds (same as Yii1).
 * Input/output uses HH:MM format for backward compatibility.
 */
class WorkReportController extends BaseController
{
    /**
     * POST /api/v1/work-report/add
     * Fields: date, workreport (JSON array of report items)
     *
     * workreport item fields:
     *   projectid  (required)
     *   totaltime  (required, HH:MM)
     *   task       (required)
     *   taskid     (optional)
     *   description (optional)
     *   fromtime   (optional, HH:MM)
     *   totime     (optional, HH:MM)
     */
    public function actionAdd(): array
    {
        $post = Yii::$app->request->post();

        $date           = trim($post['date'] ?? '');
        $workreportJson = $post['workreport'] ?? '';

        if ($date === '') {
            return $this->error('Date is missing');
        }
        if ($workreportJson === '') {
            return $this->error('Work report is missing');
        }

        $developer = $this->getLoginDeveloper();
        if (!$developer) {
            return $this->error('Developer profile not found');
        }

        $workreportArray = json_decode($workreportJson, true);
        if (!is_array($workreportArray) || count($workreportArray) < 1) {
            return $this->error('Work report is empty');
        }

        $date = date('Y-m-d', strtotime($date));

        foreach ($workreportArray as $report) {
            if (empty(trim((string)($report['projectid'] ?? '')))) {
                return $this->error('Project is missing');
            }
            if (empty(trim((string)($report['totaltime'] ?? '')))) {
                return $this->error('Total Time is missing');
            }
            if (empty(trim((string)($report['task'] ?? '')))) {
                return $this->error('Task is missing');
            }

            $project = Project::findOne((int)$report['projectid']);
            if (!$project) {
                return $this->error('Project not found: ' . $report['projectid']);
            }

            $totalSeconds = $this->hhmmToSeconds((string)$report['totaltime']);

            $model                    = new WorkReport();
            $model->developer_id      = $developer->id;
            $model->date              = $date;
            $model->project_id        = (int)$report['projectid'];
            $model->client_id         = (int)$project->client_id;
            $model->task_id           = !empty($report['taskid']) ? (int)$report['taskid'] : null;
            $model->task              = trim((string)$report['task']);
            $model->description       = trim((string)($report['description'] ?? ''));
            $model->from_time         = !empty($report['fromtime']) ? date('h:i A', strtotime((string)$report['fromtime'])) : '';
            $model->to_time           = !empty($report['totime']) ? date('h:i A', strtotime((string)$report['totime'])) : '';
            $model->total_time        = $totalSeconds;
            $model->last_updated_time = date('Y-m-d H:i:s');
            $model->data_source       = WorkReport::SOURCE_API;
            $model->status            = 1;

            if (!$model->save()) {
                return $this->error('Failed to save work report. Try again.');
            }
        }

        return $this->success([], 'Work report added successfully');
    }

    /**
     * POST /api/v1/work-report/my-list
     * Fields: pagenumber, date, project, task, from_date, to_date (all optional)
     */
    public function actionMyList(): array
    {
        $post = Yii::$app->request->post();
        $page = max(1, (int)($post['pagenumber'] ?? 1));

        $developer = $this->getLoginDeveloper();
        if (!$developer) {
            return $this->error('Developer profile not found');
        }

        $query = WorkReport::find()
            ->alias('a')
            ->joinWith(['developer', 'client', 'project'])
            ->where(['a.status' => 1, 'a.developer_id' => $developer->id])
            ->orderBy(['a.work_id' => SORT_DESC]);

        if (!empty($post['date'])) {
            $query->andWhere(['a.date' => date('Y-m-d', strtotime($post['date']))]);
        }
        if (!empty($post['project'])) {
            $query->andWhere(['like', 'project.title', $post['project']]);
        }
        if (!empty($post['task'])) {
            $query->andWhere(['like', 'a.task', $post['task']]);
        }

        $fromDate = !empty($post['from_date']) ? date('Y-m-d', strtotime($post['from_date'])) : '';
        $toDate   = !empty($post['to_date']) ? date('Y-m-d', strtotime($post['to_date'])) : '';

        if ($fromDate !== '' && $toDate !== '') {
            $query->andWhere(['between', 'a.date', $fromDate, $toDate]);
        } elseif ($fromDate !== '') {
            $query->andWhere(['a.date' => $fromDate]);
        } elseif ($toDate !== '') {
            $query->andWhere(['a.date' => $toDate]);
        }

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        // Compute total time across all matching records
        $totalSeconds = (int)WorkReport::find()
            ->alias('a')
            ->joinWith(['developer', 'client', 'project'])
            ->where(['a.status' => 1, 'a.developer_id' => $developer->id])
            ->sum('a.total_time');

        $pagination = $this->paginate((int)$count, $page);
        $results    = $query->offset($pagination['offset'])->limit($pagination['limit'])->all();

        if (empty($results)) {
            return $this->error('Page number not found');
        }

        $list = [];
        foreach ($results as $wr) {
            $list[] = [
                'workid'    => $wr->work_id,
                'date'      => date('d-M-Y', strtotime($wr->date)),
                'project'   => $wr->project->title ?? '',
                'task'      => $wr->task,
                'totaltime' => $this->secondsToHhmm((int)$wr->total_time),
            ];
        }

        return [
            'status'        => 1,
            'msg'           => '',
            'totalpages'    => $pagination['totalPages'],
            'currentpageno' => $pagination['currentPage'],
            'overalltime'   => $this->secondsToHhmm($totalSeconds),
            'workreport'    => $list,
        ];
    }

    /**
     * POST /api/v1/work-report/view
     * Fields: workid (required)
     */
    public function actionView(): array
    {
        $post = Yii::$app->request->post();
        $workId = trim($post['workid'] ?? '');

        if ($workId === '') {
            return $this->error('Work ID is missing');
        }

        $developer = $this->getLoginDeveloper();
        if (!$developer) {
            return $this->error('Developer profile not found');
        }

        $wr = WorkReport::find()
            ->where(['work_id' => (int)$workId, 'status' => 1, 'developer_id' => $developer->id])
            ->with(['developer', 'project', 'client'])
            ->one();

        if (!$wr) {
            return $this->error('Work ID is invalid');
        }

        return $this->success([
            'workid'      => $wr->work_id,
            'developer'   => $wr->developer->developer_name ?? '',
            'project'     => $wr->project->title ?? '',
            'client'      => $wr->client->client_name ?? '',
            'date'        => $wr->date,
            'fromtime'    => $wr->from_time ? date('H:i:s', strtotime($wr->from_time)) : '',
            'totime'      => $wr->to_time ? date('H:i:s', strtotime($wr->to_time)) : '',
            'totaltime'   => $this->secondsToHhmm((int)$wr->total_time),
            'task'        => $wr->task,
            'description' => $wr->description ?? '',
        ], '');
    }

    /**
     * POST /api/v1/work-report/update
     * Fields: workid, task, totaltime (required); fromtime, totime, description (optional)
     */
    public function actionUpdate(): array
    {
        $post = Yii::$app->request->post();

        $workId    = trim($post['workid'] ?? '');
        $task      = trim($post['task'] ?? '');
        $totaltime = trim($post['totaltime'] ?? '');

        if ($workId === '') return $this->error('Work ID is missing');
        if ($task === '')   return $this->error('Task is missing');
        if ($totaltime === '') return $this->error('Total time is missing');

        $developer = $this->getLoginDeveloper();
        if (!$developer) {
            return $this->error('Developer profile not found');
        }

        $wr = WorkReport::find()
            ->where(['work_id' => (int)$workId, 'status' => 1, 'developer_id' => $developer->id])
            ->one();

        if (!$wr) {
            return $this->error('Work ID is invalid');
        }

        $wr->task              = $task;
        $wr->description       = trim($post['description'] ?? '');
        $wr->from_time         = !empty($post['fromtime']) ? date('h:i A', strtotime($post['fromtime'])) : '';
        $wr->to_time           = !empty($post['totime']) ? date('h:i A', strtotime($post['totime'])) : '';
        $wr->total_time        = $this->hhmmToSeconds($totaltime);
        $wr->last_updated_time = date('Y-m-d H:i:s');

        if ($wr->save(false)) {
            return $this->success([], 'Work report updated successfully');
        }

        return $this->error('Failed to update work report. Try again!');
    }
}
