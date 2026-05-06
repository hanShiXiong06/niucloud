<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\adminapi\controller;

use addon\recycle_quote_spider\app\service\admin\QuoteLogService;
use core\base\BaseAdminController;

class Log extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['status', ''],
        ]);
        return success((new QuoteLogService())->getPage($data));
    }
}
