<?php

namespace neverstale\craft\controllers;

use craft\web\Controller;
use craft\helpers\Queue;
use craft\web\Response;
use neverstale\craft\jobs\ScanEntryTypeJob;
use neverstale\craft\enums\Permission;

class ScanController extends BaseController
{
    public function actionBatch(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Permission::Scan->value);

        $entryTypeIds = $this->request->getBodyParam('entryTypes', []);

        foreach ($entryTypeIds as $entryTypeId) {
            Queue::push(new ScanEntryTypeJob([
                'entryTypeId' => $entryTypeId,
            ]));
        }

        return $this->respondWithSuccess('Content scan jobs queued successfully');
    }
}
