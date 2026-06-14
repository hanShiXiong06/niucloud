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

    /**
     * 大批量初始数据请用 LOAD DATA 灌库(本地已去重 dict/data)，不走网页上传。
     * 此处仅给提示，避免误用导致 50 万行压垮服务器。
     */
    public function import()
    {
        return fail('检测目录为50万级数据，请用 sql/load_check_data.sql 走 LOAD DATA 灌库，不支持网页上传。');
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
