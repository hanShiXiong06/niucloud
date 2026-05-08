<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\adminapi\controller;

use addon\recycle_quote_spider\app\service\admin\QuoteItemService;
use core\base\BaseAdminController;

class Item extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['category_id', ''],
            ['category_ids', ''],
            ['is_show', ''],
            ['is_hot', ''],
            ['follow_source', ''],
            ['has_update', ''],
            ['is_image_quote', ''],
            ['brand', ''],
            ['tab', ''],
            ['quote_type', ''],
            ['keyword', ''],
        ]);
        return success((new QuoteItemService())->getPage($data));
    }

    public function filterOptions()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['category_id', ''],
            ['category_ids', ''],
        ]);
        return success((new QuoteItemService())->getFilterOptions($data));
    }

    public function info(int $id)
    {
        return success((new QuoteItemService())->getInfo($id));
    }

    public function add()
    {
        $data = $this->request->params($this->addParams(), false);
        return success('ADD_SUCCESS', ['id' => (new QuoteItemService())->addItem($data)]);
    }

    public function addRow()
    {
        $data = $this->request->params([
            ['source_id', 0],
            ['item_id', 0],
            ['model_name', ''],
            ['brand', ''],
            ['tab', ''],
            ['keywords', ''],
            ['remark', ''],
            ['columns', []],
            ['manual_prices', []],
            ['is_show', 1],
            ['sort', 0],
            ['follow_source', 0],
        ], false);
        return success('ADD_SUCCESS', ['id' => (new QuoteItemService())->addRow($data)]);
    }

    public function rows()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['item_id', ''],
            ['brand', ''],
            ['tab', ''],
            ['is_show', ''],
            ['follow_source', ''],
            ['has_update', ''],
            ['keyword', ''],
        ]);
        return success((new QuoteItemService())->rows($data));
    }

    public function edit(int $id)
    {
        $data = $this->onlyProvided($this->request->params($this->editParams(), false));
        (new QuoteItemService())->editItem($id, $data);
        return success('EDIT_SUCCESS');
    }

    public function editRow(int $id)
    {
        $data = $this->onlyProvided($this->request->params([
            ['model_name', ''],
            ['brand', ''],
            ['tab', ''],
            ['keywords', ''],
            ['remark', ''],
            ['manual_prices', []],
            ['is_show', ''],
            ['sort', ''],
            ['follow_source', ''],
            ['adjust_type', ''],
            ['adjust_value', ''],
            ['adjust_ratio', ''],
            ['round_mode', ''],
        ], false));
        (new QuoteItemService())->editRow($id, $data);
        return success('EDIT_SUCCESS');
    }

    private function editParams(): array
    {
        return [
            ['name', ''],
            ['brand', ''],
            ['tab', ''],
            ['keywords', ''],
            ['quote_type', ''],
            ['is_image_quote', ''],
            ['image', ''],
            ['timage', ''],
            ['bimage', ''],
            ['icon', ''],
            ['notice_text', ''],
            ['is_show', ''],
            ['is_hot', ''],
            ['sort', ''],
            ['follow_source', ''],
            ['adjust_type', ''],
            ['adjust_value', ''],
            ['adjust_ratio', ''],
            ['round_mode', ''],
        ];
    }

    private function addParams(): array
    {
        return [
            ['source_id', 0],
            ['category_id', 0],
            ['name', ''],
            ['brand', ''],
            ['tab', ''],
            ['keywords', ''],
            ['quote_type', 'manual'],
            ['is_image_quote', 0],
            ['image', ''],
            ['timage', ''],
            ['bimage', ''],
            ['icon', ''],
            ['notice_text', ''],
            ['is_show', 1],
            ['is_hot', 0],
            ['sort', 0],
            ['follow_source', 0],
            ['columns', []],
        ];
    }

    private function onlyProvided(array $data): array
    {
        $input = $this->request->param();
        foreach (array_keys($data) as $key) {
            if (!array_key_exists($key, $input)) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}
