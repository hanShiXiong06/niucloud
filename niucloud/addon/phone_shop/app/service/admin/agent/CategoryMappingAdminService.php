<?php

namespace addon\phone_shop\app\service\admin\agent;

use addon\phone_shop\app\job\AgentGoodsFullSync;
use addon\phone_shop\app\model\agent\PhoneShopAgent;
use addon\phone_shop\app\model\agent\PhoneShopCategoryMapping;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\service\core\agent\AgentConfigService;
use addon\phone_shop\app\service\core\agent\CategoryMappingService;
use addon\phone_shop\app\service\core\agent\AgentSyncMonitorService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/** 分类映射后台工作台。 */
class CategoryMappingAdminService extends BaseAdminService
{
    protected CategoryMappingService $mappingService;

    public function __construct()
    {
        parent::__construct();
        $this->model = new PhoneShopCategoryMapping();
        $this->mappingService = new CategoryMappingService();
        $this->mappingService->ensureSchema();
    }

    public function getPage(array $where = []): array
    {
        [$masterSiteId, $agentSiteId] = $this->relation((int)($where['agent_site_id'] ?? 0));
        $condition = [
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
        ];
        if (($where['status'] ?? '') !== '') {
            $condition[] = ['status', '=', (string)$where['status']];
        }

        $query = $this->model->where($condition);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $masterIds = Db::name('phone_shop_goods_category')
                ->where('site_id', '=', $masterSiteId)
                ->where(function ($q) use ($keyword) {
                    $q->where('category_name', 'like', '%' . $keyword . '%')
                        ->whereOr('category_full_name', 'like', '%' . $keyword . '%');
                })->column('category_id');
            $agentIds = Db::name('phone_shop_goods_category')
                ->where('site_id', '=', $agentSiteId)
                ->where(function ($q) use ($keyword) {
                    $q->where('category_name', 'like', '%' . $keyword . '%')
                        ->whereOr('category_full_name', 'like', '%' . $keyword . '%');
                })->column('category_id');
            $query->where(function ($q) use ($masterIds, $agentIds) {
                if ($masterIds) $q->whereIn('master_category_id', array_map('intval', $masterIds));
                if ($agentIds) {
                    $masterIds ? $q->whereOrIn('agent_category_id', array_map('intval', $agentIds))
                        : $q->whereIn('agent_category_id', array_map('intval', $agentIds));
                }
                if (!$masterIds && !$agentIds) $q->where('mapping_id', '=', 0);
            });
        }

        $list = $this->pageQuery($query->order('status asc,master_category_id asc'));
        $this->enrichRows($list['data'], $masterSiteId, $agentSiteId);
        $list['master_site_id'] = $masterSiteId;
        $list['agent_site_id'] = $agentSiteId;
        return $list;
    }

    public function summary(int $agentSiteId = 0): array
    {
        [$masterSiteId, $agentSiteId] = $this->relation($agentSiteId);
        return $this->mappingService->summary($masterSiteId, $agentSiteId) + [
            'master_site_id' => $masterSiteId,
            'agent_site_id' => $agentSiteId,
        ];
    }

    public function scan(int $agentSiteId = 0): array
    {
        [$masterSiteId, $agentSiteId, $relation] = $this->relation($agentSiteId, true);
        $result = $this->mappingService->scan($masterSiteId, $agentSiteId);
        if ((int)$relation->status === 1) $this->queueGoodsSync($masterSiteId, $agentSiteId);
        return $result + ['master_site_id' => $masterSiteId, 'agent_site_id' => $agentSiteId];
    }

    public function options(int $agentSiteId = 0): array
    {
        [, $agentSiteId] = $this->relation($agentSiteId);
        return (new Category())->where('site_id', '=', $agentSiteId)
            ->field('category_id,category_name,category_full_name,pid,level,is_show,source_site_id')
            ->order('level asc,sort desc,category_id asc')->select()->toArray();
    }

    public function mapExisting(int $mappingId, int $agentCategoryId): void
    {
        [$row, $masterSiteId, $agentSiteId, $relation] = $this->mapping($mappingId);
        $this->assertParentMapped($row, $masterSiteId, $agentSiteId, $agentCategoryId);
        $this->mappingService->mapExisting(
            $masterSiteId,
            $agentSiteId,
            (int)$row['master_category_id'],
            $agentCategoryId,
            (int)$this->uid
        );
        if ((int)$relation->status === 1) $this->queueGoodsSync($masterSiteId, $agentSiteId);
    }

    public function createAndMap(int $mappingId): int
    {
        [$row, $masterSiteId, $agentSiteId, $relation] = $this->mapping($mappingId);
        $master = (new Category())->where([
            ['site_id', '=', $masterSiteId],
            ['category_id', '=', (int)$row['master_category_id']],
        ])->findOrEmpty();
        if ($master->isEmpty()) throw new AdminException('主站分类已不存在');

        $parentId = 0;
        if ((int)$master->pid > 0) {
            $parentId = (int)$this->model->where([
                ['master_site_id', '=', $masterSiteId],
                ['agent_site_id', '=', $agentSiteId],
                ['master_category_id', '=', (int)$master->pid],
                ['status', '=', CategoryMappingService::STATUS_MAPPED],
            ])->value('agent_category_id');
            if ($parentId <= 0) throw new AdminException('请先处理该分类的上级分类映射');
        }

        $duplicate = (new Category())->where([
            ['site_id', '=', $agentSiteId],
            ['pid', '=', $parentId],
            ['category_name', '=', (string)$master->category_name],
        ])->findOrEmpty();
        if (!$duplicate->isEmpty()) {
            throw new AdminException('子站同级已有同名分类，请选择“关联已有分类”');
        }

        Db::startTrans();
        try {
            $now = time();
            $created = (new Category())->create([
                'site_id' => $agentSiteId,
                'category_no' => 0,
                'source_site_id' => 0,
                'category_name' => (string)$master->category_name,
                'image' => (string)$master->image,
                'level' => (int)$master->level,
                'pid' => $parentId,
                'category_full_name' => $this->childFullName($agentSiteId, $parentId, (string)$master->category_name),
                'is_show' => (int)$master->is_show,
                'sort' => (int)$master->sort,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            $categoryId = (int)$created->category_id;
            $this->mappingService->mapExisting(
                $masterSiteId,
                $agentSiteId,
                (int)$master->category_id,
                $categoryId,
                (int)$this->uid,
                'manual_created'
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        if ((int)$relation->status === 1) $this->queueGoodsSync($masterSiteId, $agentSiteId);
        return $categoryId;
    }

    public function ignore(int $mappingId): void
    {
        [$row, $masterSiteId, $agentSiteId, $relation] = $this->mapping($mappingId);
        $this->mappingService->ignore(
            $masterSiteId,
            $agentSiteId,
            (int)$row['master_category_id'],
            (int)$this->uid
        );
        if ((int)$relation->status === 1) $this->queueGoodsSync($masterSiteId, $agentSiteId);
    }

    /** @return array{0:int,1:int,2?:PhoneShopAgent} */
    protected function relation(int $requestedAgentId = 0, bool $withModel = false): array
    {
        $masterSiteId = (new AgentConfigService())->getMasterSiteId();
        $agentSiteId = $this->site_id === $masterSiteId ? $requestedAgentId : (int)$this->site_id;
        if ($agentSiteId <= 0) throw new AdminException('请选择需要处理的子站');
        $relation = (new PhoneShopAgent())->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
        ])->findOrEmpty();
        if ($relation->isEmpty()) throw new AdminException('站点跟随关系不存在');
        if ($this->site_id !== $masterSiteId && $this->site_id !== $agentSiteId) {
            throw new AdminException('无权处理该站点分类映射');
        }
        return $withModel ? [$masterSiteId, $agentSiteId, $relation] : [$masterSiteId, $agentSiteId];
    }

    /** @return array{0:array,1:int,2:int,3:PhoneShopAgent} */
    protected function mapping(int $mappingId): array
    {
        $row = $this->model->where('mapping_id', '=', $mappingId)->findOrEmpty();
        if ($row->isEmpty()) throw new AdminException('分类映射记录不存在，请先扫描');
        [$masterSiteId, $agentSiteId, $relation] = $this->relation((int)$row->agent_site_id, true);
        if ((int)$row->master_site_id !== $masterSiteId || (int)$row->agent_site_id !== $agentSiteId) {
            throw new AdminException('无权处理该分类映射');
        }
        return [$row->toArray(), $masterSiteId, $agentSiteId, $relation];
    }

    protected function assertParentMapped(array $row, int $masterSiteId, int $agentSiteId, int $agentCategoryId): void
    {
        $master = (new Category())->where([
            ['site_id', '=', $masterSiteId],
            ['category_id', '=', (int)$row['master_category_id']],
        ])->field('pid,level')->findOrEmpty();
        $target = (new Category())->where([
            ['site_id', '=', $agentSiteId],
            ['category_id', '=', $agentCategoryId],
        ])->field('pid,level')->findOrEmpty();
        if ($master->isEmpty() || $target->isEmpty()) throw new AdminException('分类不存在');
        if ((int)$master->level !== (int)$target->level) throw new AdminException('只能关联相同层级的分类');
        if ((int)$master->pid <= 0) {
            if ((int)$target->pid !== 0) throw new AdminException('一级分类只能关联子站一级分类');
            return;
        }
        $mappedParent = (int)$this->model->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
            ['master_category_id', '=', (int)$master->pid],
            ['status', '=', CategoryMappingService::STATUS_MAPPED],
        ])->value('agent_category_id');
        if ($mappedParent <= 0) throw new AdminException('请先处理上级分类映射');
        if ((int)$target->pid !== $mappedParent) throw new AdminException('所选分类不在已映射的上级分类下');
    }

    protected function childFullName(int $agentSiteId, int $parentId, string $name): string
    {
        if ($parentId <= 0) return $name;
        $parent = (new Category())->where([
            ['site_id', '=', $agentSiteId], ['category_id', '=', $parentId],
        ])->field('category_full_name,category_name')->findOrEmpty();
        if ($parent->isEmpty()) throw new AdminException('子站上级分类不存在');
        $prefix = trim((string)($parent->category_full_name ?: $parent->category_name));
        return $prefix === '' ? $name : $prefix . '/' . $name;
    }

    protected function enrichRows(array &$rows, int $masterSiteId, int $agentSiteId): void
    {
        if (!$rows) return;
        $masterIds = array_map('intval', array_column($rows, 'master_category_id'));
        $agentIds = array_filter(array_map('intval', array_merge(
            array_column($rows, 'agent_category_id'),
            ...array_map(static fn($row) => (array)($row['candidate_ids'] ?? []), $rows)
        )));
        $masterMap = (new Category())->where('site_id', '=', $masterSiteId)->whereIn('category_id', $masterIds)
            ->field('category_id,category_name,category_full_name,pid,level')->select()->column(null, 'category_id');
        $agentMap = $agentIds ? (new Category())->where('site_id', '=', $agentSiteId)->whereIn('category_id', $agentIds)
            ->field('category_id,category_name,category_full_name,pid,level')->select()->column(null, 'category_id') : [];
        foreach ($rows as &$row) {
            $master = $masterMap[(int)$row['master_category_id']] ?? (array)($row['master_snapshot'] ?? []);
            $target = $agentMap[(int)$row['agent_category_id']] ?? [];
            $row['master_name'] = (string)(($master['category_full_name'] ?? '') ?: ($master['category_name'] ?? '已删除分类'));
            $row['master_level'] = (int)($master['level'] ?? 0);
            $row['agent_name'] = (string)(($target['category_full_name'] ?? '') ?: ($target['category_name'] ?? ''));
            $row['candidate_options'] = [];
            foreach ((array)($row['candidate_ids'] ?? []) as $candidateId) {
                $candidate = $agentMap[(int)$candidateId] ?? null;
                if ($candidate) {
                    $row['candidate_options'][] = [
                        'category_id' => (int)$candidateId,
                        'name' => (string)($candidate['category_full_name'] ?: $candidate['category_name']),
                    ];
                }
            }
        }
    }

    protected function queueGoodsSync(int $masterSiteId, int $agentSiteId): void
    {
        $runId = (new AgentSyncMonitorService())->begin($masterSiteId, $agentSiteId, 'category');
        AgentGoodsFullSync::dispatch([
            'masterSiteId' => $masterSiteId,
            'agentSiteId' => $agentSiteId,
            'syncRunId' => $runId,
        ]);
    }
}
