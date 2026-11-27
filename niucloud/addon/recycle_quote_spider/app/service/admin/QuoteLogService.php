<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteSyncLog;
use core\base\BaseAdminService;

class QuoteLogService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new QuoteSyncLog();
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $search = $this->model
            ->withSearch(['site_id', 'source_id', 'status'], $where)
            ->order('id desc');
        return $this->pageQuery($search);
    }
}
