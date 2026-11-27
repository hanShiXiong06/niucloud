<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpPrintService;
use core\base\BaseAdminController;

class ErpPrint extends BaseAdminController
{
    public function meta() { return success((new ErpPrintService())->meta()); }
    public function printers() { return success((new ErpPrintService())->printers()); }
    public function savePrinter(int $id) { return success(['id' => (new ErpPrintService())->savePrinter($this->request->params([
        ['printer_name', ''], ['driver', ''], ['print_type', 'receipt'], ['paper_width', 58], ['config', []], ['copies', 1], ['is_default', 0], ['status', 1], ['sort', 0], ['remark', '']
    ]), $id)]); }
    public function deletePrinter(int $id) { return success((new ErpPrintService())->deletePrinter($id)); }
    public function testPrinter(int $id) { return success((new ErpPrintService())->testPrinter($id)); }
    public function templates() { return success((new ErpPrintService())->templates()); }
    public function saveTemplate(int $id) { return success(['id' => (new ErpPrintService())->saveTemplate($this->request->params([
        ['template_name', ''], ['print_type', 'receipt'], ['layout_mode', 'native'], ['paper_width', 58], ['content', ''], ['is_default', 0], ['status', 1], ['sort', 0]
    ]), $id)]); }
    public function scenes() { return success((new ErpPrintService())->scenes()); }
    public function saveScene(int $id) { return success((new ErpPrintService())->saveScene($this->request->params([
        ['printer_id', 0], ['template_id', 0], ['auto_print', 0], ['enabled', 0], ['copies', 1], ['granularity', 'order']
    ]), $id)); }
    public function jobs() { return success((new ErpPrintService())->jobs($this->request->params([['status', ''], ['scene_key', ''], ['keyword', ''], ['page', 1], ['limit', 20]]))); }
    public function manual() { $p = $this->request->params([['scene_key', ''], ['biz_type', ''], ['biz_id', 0], ['extra', []]]); return success((new ErpPrintService())->print((string)$p['scene_key'], (string)$p['biz_type'], (int)$p['biz_id'], (array)$p['extra'])); }
    public function retry(int $id) { return success((new ErpPrintService())->retry($id)); }
    public function clientComplete(int $id) { $p = $this->request->params([['success', 0], ['message', '']]); return success((new ErpPrintService())->clientComplete($id, (int)$p['success'] === 1, (string)$p['message'])); }
}
