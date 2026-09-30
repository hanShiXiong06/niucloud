<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 站点代理订阅关系（后台控制器）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\adminapi\controller\agent;

use addon\phone_shop\app\service\admin\agent\AgentRelationService;
use addon\phone_shop\app\service\admin\agent\CategoryMappingAdminService;
use addon\phone_shop\app\service\core\agent\AgentConfigService;
use core\base\BaseAdminController;

/**
 * 站点代理订阅关系控制器
 * Class Agent
 * @package addon\phone_shop\app\adminapi\controller\agent
 */
class Agent extends BaseAdminController
{
    /**
     * 代理关系分页
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ['status', ''],
        ]);
        return success((new AgentRelationService())->getPage($data));
    }

    /**
     * 建立站点跟随关系（主站指定从站，或从站关注主站）
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['agent_site_id', 0],
            ['markup_value', 0],
            ['subscribe_category', 1],
            ['ref_sync_mode', null],
            ['ref_sync_interval', 60],
            ['ref_auto_create_category', 1],
        ]);
        $data = array_filter($data, fn($v) => $v !== null);
        return success('ADD_SUCCESS', ['id' => (new AgentRelationService())->add($data)]);
    }

    /**
     * 编辑（加价/订阅/状态）
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['markup_value', null],
            ['subscribe_category', null],
            ['status', null],
            ['ref_sync_mode', null],
            ['ref_sync_interval', null],
            ['ref_auto_create_category', null],
        ]);
        $data = array_filter($data, fn($v) => $v !== null);
        (new AgentRelationService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除关系
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new AgentRelationService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /** 子站按站点关系刷新主站全部商品。 */
    public function syncGoods()
    {
        return success('SYNC_SUCCESS', (new AgentRelationService())->syncMasterGoods());
    }

    /** 手动全量校准的浏览器分批执行接口。 */
    public function syncGoodsStep()
    {
        $data = $this->request->params([
            ['run_id', 0],
            ['cursor', 0],
            ['limit', 25],
        ]);
        return success('SYNC_SUCCESS', (new AgentRelationService())->syncMasterGoodsStep(
            (int)$data['run_id'],
            (int)$data['cursor'],
            (int)$data['limit']
        ));
    }

    /** 子站货盘数量、可售状态、同步差异与最近一次任务。 */
    public function dashboard()
    {
        $data = $this->request->params([
            ['agent_site_id', 0],
        ]);
        return success((new AgentRelationService())->dashboard((int)$data['agent_site_id']));
    }

    public function referenceSyncInfo()
    {
        $data = $this->request->params([['agent_site_id', 0]]);
        return success((new AgentRelationService())->referenceSyncInfo((int)$data['agent_site_id']));
    }

    public function startReferenceSync()
    {
        $data = $this->request->params([['agent_site_id', 0]]);
        return success((new AgentRelationService())->startReferenceSync((int)$data['agent_site_id']));
    }

    public function referenceSyncStep()
    {
        $data = $this->request->params([['agent_site_id', 0], ['token', ''], ['revision', 0]]);
        return success((new AgentRelationService())->referenceSyncStep((int)$data['agent_site_id'], (string)$data['token'], (int)$data['revision']));
    }

    public function categoryMappingPages()
    {
        $data = $this->request->params([
            ['agent_site_id', 0],
            ['status', ''],
            ['keyword', ''],
        ]);
        return success((new CategoryMappingAdminService())->getPage($data));
    }

    public function categoryMappingSummary()
    {
        $data = $this->request->params([['agent_site_id', 0]]);
        return success((new CategoryMappingAdminService())->summary((int)$data['agent_site_id']));
    }

    public function scanCategoryMappings()
    {
        $data = $this->request->params([['agent_site_id', 0]]);
        return success('扫描完成', (new CategoryMappingAdminService())->scan((int)$data['agent_site_id']));
    }

    public function categoryMappingOptions()
    {
        $data = $this->request->params([['agent_site_id', 0]]);
        return success((new CategoryMappingAdminService())->options((int)$data['agent_site_id']));
    }

    public function mapCategory(int $id)
    {
        $data = $this->request->params([['agent_category_id', 0]]);
        (new CategoryMappingAdminService())->mapExisting($id, (int)$data['agent_category_id']);
        return success('关联成功');
    }

    public function createCategoryMapping(int $id)
    {
        $categoryId = (new CategoryMappingAdminService())->createAndMap($id);
        return success('创建并关联成功', ['category_id' => $categoryId]);
    }

    public function ignoreCategoryMapping(int $id)
    {
        (new CategoryMappingAdminService())->ignore($id);
        return success('已忽略');
    }

    /**
     * 取主站配置（含当前站是否主站）
     * @return \think\Response
     */
    public function masterConfig()
    {
        $svc = new AgentConfigService();
        $displayConfig = $svc->getDisplayConfig((int)$this->request->siteId());
        $masterSiteId = $displayConfig['master_site_id'];
        return success([
            'master_site_id' => $masterSiteId,
            'is_master_site' => $this->request->siteId() == $masterSiteId ? 1 : 0,
            'display_config' => $displayConfig,
        ]);
    }

    public function setDisplayConfig()
    {
        $data = $this->request->params([['agent_name', '']]);
        $siteId = (int)$this->request->siteId();
        $svc = new AgentConfigService();
        $svc->setDisplayConfig($siteId, $data);
        return success('SET_SUCCESS', $svc->getDisplayConfig($siteId));
    }

    /**
     * 设置主站ID（仅平台/主站可操作）
     * @return \think\Response
     */
    public function setMasterConfig()
    {
        $data = $this->request->params([
            ['master_site_id', 0],
        ]);
        (new AgentConfigService())->setMasterSiteId((int) $data['master_site_id']);
        return success('SET_SUCCESS');
    }
}
