<?php

namespace api\modules\v1\controllers;

use common\models\Project;
use Yii;

/**
 * Project endpoints — migrated from Yii1 WebserviceController:
 *   actionProjectlist() → POST /api/v1/project/list
 */
class ProjectController extends BaseController
{
    /**
     * POST /api/v1/project/list
     * Fields: pagenumber (optional, default 1), searchkey (optional)
     *
     * Migrated from Yii1 actionProjectlist():
     *   - Returns active, non-completed projects (status=1, project_status!=3)
     *   - 10 items per page, sorted by title ASC
     */
    public function actionList(): array
    {
        $post      = Yii::$app->request->post();
        $page      = max(1, (int)($post['pagenumber'] ?? 1));
        $searchKey = trim($post['searchkey'] ?? '');

        $query = Project::find()
            ->where(['status' => 1])
            ->andWhere(['!=', 'project_status', 3])
            ->orderBy(['title' => SORT_ASC]);

        if ($searchKey !== '') {
            $query->andWhere(['like', 'title', $searchKey]);
        }

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        $pagination = $this->paginate((int)$count, $page);

        $results = $query
            ->with(['client'])
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $projectList = [];
        foreach ($results as $project) {
            $projectList[] = [
                'projectid'   => $project->id,
                'title'       => $project->title,
                'client'      => $project->client->client_name ?? '',
                'description' => $project->description ?? '',
                'date'        => $project->date ?? '',
            ];
        }

        return array_merge(
            ['status' => 1, 'msg' => ''],
            [
                'totalpages'    => $pagination['totalPages'],
                'currentpageno' => $pagination['currentPage'],
                'project_list'  => $projectList,
            ]
        );
    }
}
