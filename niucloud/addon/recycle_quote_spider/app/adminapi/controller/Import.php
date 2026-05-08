<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\adminapi\controller;

use addon\recycle_quote_spider\app\service\admin\QuoteImportService;
use core\base\BaseAdminController;

class Import extends BaseAdminController
{
    public function upload()
    {
        $sourceId = (int)$this->request->param('source_id', 0);
        $file = $this->request->file('file');
        return success('上传成功', (new QuoteImportService())->upload($file, $sourceId));
    }

    public function preview()
    {
        $data = $this->request->params([
            ['task_id', 0],
            ['file_path', ''],
            ['sheet_name', ''],
            ['header_row', 1],
            ['preview_count', 20],
        ]);
        return success('解析成功', (new QuoteImportService())->preview($data));
    }

    public function confirm()
    {
        $data = $this->request->params([
            ['task_id', 0],
            ['source_id', 0],
            ['category_id', 0],
            ['item_id', 0],
            ['item_name', ''],
            ['notice_text', ''],
            ['brand', ''],
            ['tab', ''],
            ['sheet_name', ''],
            ['header_row', 1],
            ['mapping', []],
            ['mode', 'replace'],
        ], false);
        return success('导入成功', (new QuoteImportService())->confirm($data));
    }

    public function lists()
    {
        $data = $this->request->params([
            ['source_id', ''],
            ['status', ''],
        ]);
        return success((new QuoteImportService())->getPage($data));
    }
}
