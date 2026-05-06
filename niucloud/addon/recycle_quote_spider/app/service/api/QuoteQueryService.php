<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\api;

use addon\recycle_quote_spider\app\model\QuoteCategory;
use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use core\base\BaseApiService;
use core\exception\CommonException;

class QuoteQueryService extends BaseApiService
{
    public function categoryTree(array $where = []): array
    {
        $query = (new QuoteCategory())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        $list = $query->order('sort asc,id asc')->select()->toArray();
        return $this->buildTree($list);
    }

    public function items(array $where = []): array
    {
        $query = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        if (!empty($where['category_id'])) {
            $query->where('category_id', (int)$where['category_id']);
        }
        if (!empty($where['keyword'])) {
            $query->whereLike('name|brand|tab|keywords|parent_name', '%' . $where['keyword'] . '%');
        }
        return $this->pageQuery($query->order('is_hot desc,sort asc,id desc'));
    }

    public function detail(int $id): array
    {
        $item = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($item)) {
            throw new CommonException('报价不存在');
        }
        $rows = (new QuoteRow())->where('site_id', $this->site_id)->where('item_id', $id)->where('is_show', 1)->order('sort asc,id asc')->select()->toArray();
        $item['rows'] = $rows;
        return $item;
    }

    private function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int)$item['parent_id'] !== $parentId) {
                continue;
            }
            $item['children'] = $this->buildTree($list, (int)$item['id']);
            $tree[] = $item;
        }
        return $tree;
    }
}
