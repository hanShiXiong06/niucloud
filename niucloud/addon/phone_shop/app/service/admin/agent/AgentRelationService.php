<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 站点代理订阅关系（后台）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\admin\agent;

use addon\phone_shop\app\model\agent\PhoneShopAgent;
use addon\phone_shop\app\job\AgentGoodsFullSync;
use addon\phone_shop\app\service\core\agent\AgentConfigService;
use addon\phone_shop\app\service\core\agent\GoodsDistributionService;
use addon\phone_shop\app\service\core\agent\RefDataSyncService;
use addon\phone_shop\app\service\core\agent\AgentSyncMonitorService;
use addon\phone_shop\app\service\core\agent\AgentReferenceSyncService;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 代理订阅关系服务（后台）
 * 主站维护「我代理了哪些子站」；子站维护「加价 / 是否订阅分类同步」。
 * Class AgentRelationService
 * @package addon\phone_shop\app\service\admin\agent
 */
class AgentRelationService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new PhoneShopAgent();
    }

    /**
     * 关系列表
     * 主站登录：看自己代理的子站；子站登录：看代理自己的主站。
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        (new AgentSyncMonitorService())->ensureSchema();
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $condition = [];
        if ($this->site_id == $masterSiteId) {
            $condition[] = [ 'master_site_id', '=', $this->site_id ];
        } else {
            $condition[] = [ 'agent_site_id', '=', $this->site_id ];
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $condition[] = [ 'status', '=', (int) $where['status'] ];
        }

        $field = 'id,master_site_id,agent_site_id,markup_value,subscribe_category,status,create_time,update_time,ref_sync_mode,ref_sync_interval,ref_auto_create_category,ref_sync_status,ref_sync_last_time,ref_sync_next_time';
        $search_model = $this->model->where($condition)->field($field)->order('create_time desc');
        $list = $this->pageQuery($search_model);

        if (!empty($list['data'])) {
            $siteModel = new \app\model\site\Site();
            foreach ($list['data'] as &$item) {
                $item['master_site_name'] = (string) $siteModel->where('site_id', $item['master_site_id'])->value('site_name');
                $item['agent_site_name']  = (string) $siteModel->where('site_id', $item['agent_site_id'])->value('site_name');
                $item = array_merge($item, AgentReferenceSyncService::settings([], $item));
            }
        }
        return $list;
    }

    /**
     * 建立站点跟随关系。
     * 主站可指定一个从站；从站发起时只能让“当前 site_id”关注配置中的主站，
     * 不能代替其他站点建立关系。
     * @param array $data
     * @return int
     */
    public function add(array $data)
    {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $isMaster = $this->site_id == $masterSiteId;
        $agentSiteId = $isMaster ? (int)($data['agent_site_id'] ?? 0) : (int)$this->site_id;
        if ($agentSiteId <= 0) {
            throw new AdminException('请选择子站');
        }
        if ($agentSiteId == $masterSiteId) {
            throw new AdminException('主站不能代理自己');
        }
        $agentSite = (new \app\model\site\Site())->where('site_id', '=', $agentSiteId)->findOrEmpty();
        if ($agentSite->isEmpty()) {
            throw new AdminException('从站不存在，请核对站点ID');
        }
        $exists = $this->model->where([
            [ 'master_site_id', '=', $masterSiteId ],
            [ 'agent_site_id', '=', $agentSiteId ],
        ])->find();
        if ($exists) {
            throw new AdminException('该代理关系已存在');
        }

        // 关系创建是整个跟随链路的入口，先补齐老站点依赖表；失败时不留下半条关系。
        (new AgentSyncMonitorService())->ensureSchema();
        $now = time();
        $settings = AgentReferenceSyncService::settings($data);
        $res = $this->model->create(array_merge([
            'master_site_id'     => $masterSiteId,
            'agent_site_id'      => $agentSiteId,
            'markup_value'       => max(0, (float) ($data['markup_value'] ?? 0)),
            'subscribe_category' => isset($data['subscribe_category']) ? (int) $data['subscribe_category'] : 1,
            'status'             => 1,
            'create_time'        => $now,
            'update_time'        => $now,
        ], $settings, ['ref_sync_next_time' => AgentReferenceSyncService::nextTime($settings + ['status' => 1], $now)]));

        // 新建关系：若订阅分类，则一次性全量补同步主站现有引用数据到该子站
        if ((int) $res->subscribe_category === 1) {
            try {
                (new AgentReferenceSyncService())->queue($masterSiteId, $agentSiteId);
            } catch (\Throwable $e) {
                // 不阻断关系创建
            }
        }
        // 站点关系一旦建立，立即同步主站全部现有货盘；以后新增/修改由单品事件增量推送。
        $this->queueFullSync($masterSiteId, $agentSiteId, 'relation');
        return (int) $res->id;
    }

    /**
     * 编辑（加价 / 订阅开关 / 状态）。
     * 子站只能改自己的加价与订阅；主站可改其名下任意关系。
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        (new AgentSyncMonitorService())->ensureSchema();
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $info = $this->model->where('id', $id)->findOrEmpty();
        if ($info->isEmpty()) {
            throw new AdminException('代理关系不存在');
        }
        // 权限：主站本人 或 该关系的子站本人
        $isMaster = ($this->site_id == $masterSiteId && $info->master_site_id == $masterSiteId);
        $isAgent  = ($this->site_id == $info->agent_site_id);
        if (!$isMaster && !$isAgent) {
            throw new AdminException('无权限修改该代理关系');
        }

        $update = [ 'update_time' => time() ];
        if (isset($data['markup_value'])) {
            $update['markup_value'] = max(0, (float) $data['markup_value']);
        }
        if (isset($data['subscribe_category'])) {
            $update['subscribe_category'] = (int) $data['subscribe_category'];
        }
        // 状态仅主站可改
        if ($isMaster && isset($data['status'])) {
            $update['status'] = (int) $data['status'];
        }
        $settings = AgentReferenceSyncService::settings($data, $info->toArray());
        $update = array_merge($update, $settings);
        if (array_diff_assoc($settings, array_intersect_key($info->toArray(), $settings)) || isset($update['status'])) {
            $update['ref_sync_next_time'] = AgentReferenceSyncService::nextTime(array_merge($info->toArray(), $update), time());
        }

        $before = (int) $info->subscribe_category;
        $beforeStatus = (int)$info->status;
        $this->model->where('id', $id)->update($update);

        // 子站从「不订阅」改为「订阅」：补同步一次
        if (isset($update['subscribe_category']) && $before === 0 && $update['subscribe_category'] === 1) {
            try {
                (new AgentReferenceSyncService())->queue((int)$info->master_site_id, (int)$info->agent_site_id);
            } catch (\Throwable $e) {
            }
        }
        if (isset($update['status']) && $beforeStatus !== (int)$update['status']) {
            if ((int)$update['status'] === 1) {
                $this->queueFullSync((int)$info->master_site_id, (int)$info->agent_site_id, 'relation');
            } else {
                (new GoodsDistributionService())->offlineAllForAgent(
                    (int)$info->master_site_id,
                    (int)$info->agent_site_id
                );
            }
        } elseif (isset($update['markup_value']) && (int)$info->status === 1) {
            // 加价属于站点关系，变化后重算该从站全部代理商品价格。
            $this->queueFullSync((int)$info->master_site_id, (int)$info->agent_site_id, 'relation');
        }
        return true;
    }

    /**
     * 删除/取消站点跟随关系。主站可删除名下关系，从站只能取消自己的关系。
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $info = $this->model->where([ [ 'id', '=', $id ], [ 'master_site_id', '=', $masterSiteId ] ])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new AdminException('代理关系不存在');
        }
        $isMaster = $this->site_id == $masterSiteId;
        $isAgent = $this->site_id == (int)$info->agent_site_id;
        if (!$isMaster && !$isAgent) {
            throw new AdminException('无权限取消该站点跟随关系');
        }
        // 删除站点关系前下架该主站在从站的全部副本。
        (new GoodsDistributionService())->offlineAllForAgent(
            (int)$info->master_site_id,
            (int)$info->agent_site_id
        );
        return (bool) $info->delete();
    }

    /**
     * 子站刷新主站全部商品，并补齐其引用资料。
     * 当前站须与主站存在启用中的代理关系。
     * @return array{count:int}
     */
    public function syncMasterGoods(): array
    {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        if ($this->site_id == $masterSiteId) {
            throw new AdminException('主站无需同步自己');
        }
        $rel = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->findOrEmpty();
        if ($rel->isEmpty()) {
            throw new AdminException('当前站点未与主站建立代理关系，请联系主站添加');
        }
        // 显式刷新时先补齐老站点表结构，再重跑引用数据和完整货盘。
        $monitor = new AgentSyncMonitorService();
        $monitor->ensureSchema();
        $refs = [];
        try {
            $refs = (new RefDataSyncService())->backfill($masterSiteId, $this->site_id);
        } catch (\Throwable $e) {
            $refs = ['error' => $e->getMessage()];
        }
        // 管理员点击的手动校准由浏览器分批推进，不依赖服务器是否启动队列消费者。
        // 老的 queued/running 批次会被明确结束，避免看板长期卡在“等待队列、扫描0”。
        $monitor->supersedeActive($masterSiteId, (int)$this->site_id);
        $runId = $monitor->begin($masterSiteId, (int)$this->site_id, 'manual');
        $total = (new GoodsDistributionService())->countEligibleMasterGoods($masterSiteId);
        // refs 返回每类同步条数：0 = 主站该项无数据或报错；>0 = 已同步
        return [
            'count' => 0,
            'run_id' => $runId,
            'queued' => 0,
            'browser_steps' => 1,
            'total' => $total,
            'refs' => $refs,
        ];
    }

    /** 浏览器驱动一批当前可售货盘，失败商品被记录但不会阻断后续商品。 */
    public function syncMasterGoodsStep(int $runId, int $cursor = 0, int $limit = 25): array
    {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        if ($this->site_id == $masterSiteId) throw new AdminException('主站无需同步自己');
        $relation = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->findOrEmpty();
        if ($relation->isEmpty()) throw new AdminException('当前站点未与主站建立启用中的跟随关系');

        $monitor = new AgentSyncMonitorService();
        $monitor->assertRun($runId, $masterSiteId, (int)$this->site_id);
        try {
            $batch = (new GoodsDistributionService())->distributeEligibleBatch(
                $masterSiteId,
                (int)$this->site_id,
                max(0, $cursor),
                $limit
            );
            $totalReport = $monitor->appendProgress($runId, $batch);
            if ((int)($batch['done'] ?? 0) === 1) $monitor->finish($runId, $totalReport);
            return array_merge($batch, $totalReport, [
                'run_id' => $runId,
                'total' => (new GoodsDistributionService())->countEligibleMasterGoods($masterSiteId),
            ]);
        } catch (\Throwable $e) {
            $monitor->fail($runId, $e);
            throw $e;
        }
    }

    /** 当前站点可直接理解的货盘概览与最近同步结果。 */
    public function dashboard(int $requestedAgentId = 0): array
    {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $agentSiteId = $this->site_id === $masterSiteId ? $requestedAgentId : (int)$this->site_id;
        if ($agentSiteId <= 0) throw new AdminException('请选择子站');
        $relation = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
        ])->findOrEmpty();
        if ($relation->isEmpty()) throw new AdminException('站点跟随关系不存在');
        return (new AgentSyncMonitorService())->dashboard($masterSiteId, $agentSiteId);
    }

    public function referenceSyncInfo(int $requestedAgentId = 0): array
    {
        [$master, $agent] = $this->referenceRelation($requestedAgentId);
        return (new AgentReferenceSyncService())->info($master, $agent);
    }

    public function startReferenceSync(int $requestedAgentId = 0): array
    {
        [$master, $agent] = $this->referenceRelation($requestedAgentId);
        return (new AgentReferenceSyncService())->start($master, $agent);
    }

    public function referenceSyncStep(int $requestedAgentId, string $token, int $revision): array
    {
        [$master, $agent] = $this->referenceRelation($requestedAgentId);
        return (new AgentReferenceSyncService())->step($master, $agent, $token, $revision);
    }

    private function referenceRelation(int $requestedAgentId): array
    {
        $master = (new AgentConfigService())->getMasterSiteId();
        $site = (int)$this->site_id;
        if ($site !== $master && $requestedAgentId > 0 && $requestedAgentId !== $site) {
            throw new AdminException('无权操作其他子站的基础资料同步');
        }
        $agent = $site === $master ? $requestedAgentId : $site;
        if ($agent <= 0 || $agent === $master) throw new AdminException('请选择有效的子站');
        return [$master, $agent];
    }

    protected function queueFullSync(int $masterSiteId, int $agentSiteId, string $trigger): int
    {
        $monitor = new AgentSyncMonitorService();
        $runId = $monitor->begin($masterSiteId, $agentSiteId, $trigger);
        AgentGoodsFullSync::dispatch([
            'masterSiteId' => $masterSiteId,
            'agentSiteId' => $agentSiteId,
            'syncRunId' => $runId,
        ]);
        return $runId;
    }

    /**
     * 取某主站「启用中」的代理子站（铺货/引用同步目标）
     * @param int $masterSiteId
     * @param bool $onlySubscribed 仅取订阅引用同步的子站
     * @return array<int, array{agent_site_id:int, markup_value:float, subscribe_category:int}>
     */
    public function getActiveAgents(int $masterSiteId, bool $onlySubscribed = false): array
    {
        $where = [
            [ 'master_site_id', '=', $masterSiteId ],
            [ 'status', '=', 1 ],
        ];
        if ($onlySubscribed) {
            $where[] = [ 'subscribe_category', '=', 1 ];
        }
        return (new PhoneShopAgent())->where($where)
            ->field('agent_site_id,markup_value,subscribe_category')
            ->select()->toArray();
    }

    /**
     * 取某子站对某主站的加价（不存在/停用返回 0）
     * @param int $masterSiteId
     * @param int $agentSiteId
     * @return float
     */
    public function getMarkup(int $masterSiteId, int $agentSiteId): float
    {
        $row = (new PhoneShopAgent())->where([
            [ 'master_site_id', '=', $masterSiteId ],
            [ 'agent_site_id', '=', $agentSiteId ],
            [ 'status', '=', 1 ],
        ])->field('markup_value')->findOrEmpty();
        return $row->isEmpty() ? 0.0 : (float) $row->markup_value;
    }
}
