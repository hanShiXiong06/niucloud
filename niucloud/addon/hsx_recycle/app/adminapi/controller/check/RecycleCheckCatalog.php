<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\check;

use addon\hsx_recycle\app\service\admin\check\RecycleCheckCatalogService;
use addon\hsx_recycle\app\service\admin\check\RecycleCheckSeverityService;
use core\base\BaseAdminController;
use think\App;

class RecycleCheckCatalog extends BaseAdminController
{
    protected RecycleCheckCatalogService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleCheckCatalogService();
    }

    /** 概览 + 最近批次 */
    public function lists()
    {
        return success([
            'summary' => $this->service->summary(),
            'batches' => $this->service->batchList(20),
        ]);
    }

    /** 上传原始拍机堂 Excel/CSV(型号,产品ID,检测项,分类,默认选项,全部选项)，返回批次+token */
    public function importUpload()
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            return fail('文件上传失败，请重试');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            return fail('请上传 Excel 或 CSV 文件');
        }
        $dir = public_path() . 'upload/check_import/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $token = 'pjt_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $file->move($dir, $token);
        return success('上传成功，开始导入', $this->service->importInit($dir . $token, $token, $file->getOriginalName()));
    }

    /** 处理一片(前端循环调用直到 done) */
    public function importChunk()
    {
        $p = $this->request->params([['batch_id', 0], ['token', ''], ['offset', 0], ['limit', 80]]);
        return success($this->service->importChunk((int)$p['batch_id'], (string)$p['token'], (int)$p['offset'], (int)$p['limit']));
    }

    /** 级别字典列表 + 统计 */
    public function severityLists()
    {
        $s = new RecycleCheckSeverityService();
        return success([
            'summary' => $s->summary(),
            'page' => $s->getPage($this->request->params([
                ['severity', ''], ['keyword', ''], ['page', 1], ['limit', 50],
            ])),
        ]);
    }

    public function severitySet(int $id)
    {
        return success((new RecycleCheckSeverityService())->setSeverity($id, (string)$this->request->param('severity', 'normal')));
    }

    public function severityBatchSet()
    {
        $p = $this->request->params([['ids', []], ['severity', 'normal']]);
        return success((new RecycleCheckSeverityService())->batchSetSeverity((array)$p['ids'], (string)$p['severity']));
    }

    public function severityByKeyword()
    {
        $p = $this->request->params([['keyword', ''], ['severity', 'abnormal']]);
        return success((new RecycleCheckSeverityService())->setByKeyword((string)$p['keyword'], (string)$p['severity']));
    }
}
