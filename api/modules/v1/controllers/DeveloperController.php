<?php

namespace api\modules\v1\controllers;

use common\models\Developer;
use Yii;

/**
 * Developer endpoints — migrated from Yii1 WebserviceController:
 *   actionDeveloperlist() → POST /api/v1/developer/list
 */
class DeveloperController extends BaseController
{
    /**
     * POST /api/v1/developer/list
     * Fields: pagenumber (optional, default 1), developer_name (optional filter)
     */
    public function actionList(): array
    {
        $post          = Yii::$app->request->post();
        $page          = max(1, (int)($post['pagenumber'] ?? 1));
        $developerName = trim($post['developer_name'] ?? '');

        $query = Developer::find()
            ->where(['status' => 1])
            ->orderBy(['developer_name' => SORT_ASC]);

        if ($developerName !== '') {
            $query->andWhere(['like', 'developer_name', $developerName]);
        }

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        $pagination = $this->paginate((int)$count, $page);

        $results = $query
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $developerList = [];
        foreach ($results as $dev) {
            $developerList[] = [
                'userid'         => $dev->user_id,
                'developer_name' => $dev->developer_name,
                'designation'    => $dev->designation ?? '',
                'address'        => $dev->address ?? '',
                'mobile'         => $dev->mobile ?? '',
                'email_id'       => $dev->email_id ?? '',
                'qualification'  => $dev->qualification ?? '',
                'profile_photo'  => $dev->profile_photo ?? '',
            ];
        }

        return array_merge(
            ['status' => 1, 'msg' => 'Success'],
            [
                'totalpages'     => $pagination['totalPages'],
                'currentpageno'  => $pagination['currentPage'],
                'developer_list' => $developerList,
            ]
        );
    }
}
