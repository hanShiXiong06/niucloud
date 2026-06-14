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

    /** 检测数据列表(全ID映射，已还原中文) */
    public function lists()
    {
        return success([
            'summary' => $this->service->summary(),
            'page' => $this->service->dataPage($this->request->params([
                ['model_key', ''], ['product_id', 0], ['page', 1], ['limit', 20],
            ])),
        ]);
    }

    /** 按型号取整套检测项 */
    public function byModel()
    {
        return success($this->service->getByModel((string)$this->request->param('model_key', '')));
    }

    /** 导入批次记录 */
    public function batches()
    {
        return success($this->service->batchList(30));
    }

    /** 上传原始 CSV(型号,产品ID,检测项,分类,默认选项,全部选项)，返回批次+token */
    public function importUpload()
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            return fail('文件上传失败，请重试');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return fail('请上传 CSV 文件（拍机堂原始表另存为 CSV UTF-8 即可）');
        }
        $dir = public_path() . 'upload/check_import/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $token = 'pjt_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.csv';
        $file->move($dir, $token);
        return success('上传成功，开始导入', $this->service->importInit($dir . $token, $token, $file->getOriginalName()));
    }

    /** 处理一片(前端循环调用直到 done) */
    public function importChunk()
    {
        $p = $this->request->params([['batch_id', 0], ['token', ''], ['offset', 0], ['limit', 2000]]);
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

    /** 设置单个选项级别 */
    public function severitySet(int $id)
    {
        return success((new RecycleCheckSeverityService())->setSeverity($id, (string)$this->request->param('severity', 'normal')));
    }

    /** 批量设置级别 */
    public function severityBatchSet()
    {
        $p = $this->request->params([['ids', []], ['severity', 'normal']]);
        return success((new RecycleCheckSeverityService())->batchSetSeverity((array)$p['ids'], (string)$p['severity']));
    }

    /** 按关键字批量打标级别 */
    public function severityByKeyword()
    {
        $p = $this->request->params([['keyword', ''], ['severity', 'abnormal']]);
        return success((new RecycleCheckSeverityService())->setByKeyword((string)$p['keyword'], (string)$p['severity']));
    }
}
