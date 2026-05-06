<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\adminapi\controller;

use addon\recycle_quote_spider\app\service\admin\QuoteSourceService;
use core\base\BaseAdminController;

class Source extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['status', ''],
        ]);
        return success((new QuoteSourceService())->getPage($data));
    }

    public function all()
    {
        return success((new QuoteSourceService())->getAll());
    }

    public function info(int $id)
    {
        return success((new QuoteSourceService())->getInfo($id));
    }

    public function add()
    {
        $data = $this->request->params($this->params(), false);
        $id = (new QuoteSourceService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    public function edit(int $id)
    {
        $data = $this->request->params($this->params(), false);
        (new QuoteSourceService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    public function del(int $id)
    {
        (new QuoteSourceService())->del($id);
        return success('DELETE_SUCCESS');
    }

    public function sync(int $id)
    {
        return success('同步完成', (new QuoteSourceService())->sync($id));
    }

    public function createDefault()
    {
        $id = (new QuoteSourceService())->createDefault();
        return success('默认报价源已创建', ['id' => $id]);
    }

    private function params(): array
    {
        return [
            ['source_key', ''],
            ['source_name', ''],
            ['provider', ''],
            ['base_url', ''],
            ['list_path', ''],
            ['detail_path', ''],
            ['request_config', []],
            ['sync_enabled', 0],
            ['sync_interval', 86400],
            ['timeout', 15],
            ['rate_limit', 300],
            ['retry_times', 1],
            ['status', 1],
        ];
    }
}
