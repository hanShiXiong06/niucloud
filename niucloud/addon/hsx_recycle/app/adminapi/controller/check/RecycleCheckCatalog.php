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

    /**
     * 导入检测目录(CSV，流式，50万行无压力)。
     * 列顺序：型号,产品ID,检测项,分类,默认选项,全部选项(用 | 分隔)。
     */
    public function import()
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            return fail('文件上传失败，请重试');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return fail('检测目录请用 CSV 导入（50万行 xlsx 会内存溢出）。可把 Excel 另存为 CSV UTF-8 再传。');
        }
        $dir = public_path() . 'upload/check_catalog/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $saveName = 'catalog_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.csv';
        $file->move($dir, $saveName);
        $absPath = $dir . $saveName;

        $source = (string)$this->request->param('source', 'paijitang');
        $result = $this->service->importCsv($absPath, $source, $file->getOriginalName());

        if (is_file($absPath)) {
            @unlink($absPath);
        }
        return success('导入完成', $result);
    }

    /** 目录列表 */
    public function lists()
    {
        return success($this->service->catalogPage($this->request->params([
            ['model_key', ''], ['group_name', ''], ['keyword', ''], ['page', 1], ['limit', 20],
        ])));
    }

    /** 导入批次记录 */
    public function batches()
    {
        return success($this->service->batchList(30));
    }

    /** 级别字典列表 + 统计 */
    public function severityLists()
    {
        $severityService = new RecycleCheckSeverityService();
        return success([
            'summary' => $severityService->summary(),
            'page' => $severityService->getPage($this->request->params([
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
