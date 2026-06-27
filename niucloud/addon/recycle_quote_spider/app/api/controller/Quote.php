<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\api\controller;

use addon\recycle_quote_spider\app\service\api\QuoteQueryService;
use core\base\BaseApiController;

class Quote extends BaseApiController
{
    public function sources()
    {
        return success((new QuoteQueryService())->sources());
    }

    public function featured()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['category_id', ''],
            ['keyword', ''],
            ['limit', 10],
            ['only_hot', ''],
        ]);
        return success((new QuoteQueryService())->featured($data));
    }

    public function categoryTree()
    {
        $data = $this->request->params([
            ['source_id', ''],
        ]);
        return success((new QuoteQueryService())->categoryTree($data));
    }

    public function items()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['category_id', ''],
            ['keyword', ''],
        ]);
        return success((new QuoteQueryService())->items($data));
    }

    public function detail(int $id)
    {
        return success((new QuoteQueryService())->detail($id));
    }

    public function priceHistory(int $id)
    {
        $days = (int)$this->request->param('days', 30);
        return success((new QuoteQueryService())->priceHistory($id, $days));
    }

    public function reportPermission()
    {
        return success((new QuoteQueryService())->reportPermission());
    }
}
