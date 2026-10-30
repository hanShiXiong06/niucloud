<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Safe read models. A staged or failed generation is never exposed as current stock. */
final class ReadService
{
    private const INVENTORY_FIELDS = 'i.id,i.merchant_id,i.connection_id,i.sync_run_id,i.source_id,i.source_store_id,i.tenant_id,'
        . 'i.model,i.memory,i.brand,i.color,i.grade,i.price,i.trade_price,i.quantity,i.imei,i.cover,i.availability,i.fetched_at,'
        . 'm.name AS merchant_name,c.provider,c.last_sync_at,c.last_sync_status,c.sync_interval_minutes';

    public function __construct(private readonly Context $context) {}

    public function inventory(array $params = [], ?int $merchantId = null): array
    {
        if ($merchantId === null) {
            $this->context->manager();
            $filter = (int) ($params['merchant_id'] ?? 0);
            $merchantId = $filter > 0 ? $filter : null;
        } else {
            $this->authorizeMerchant($merchantId);
        }
        $query = $this->inventoryQuery($merchantId);
        $keyword = Data::text($params, 'keyword', 200);
        if ($keyword !== '') $query->whereLike('i.model|i.memory|i.brand|i.color|i.imei', $this->like($keyword));
        foreach (['model', 'brand', 'memory', 'color'] as $field) {
            $value = Data::text($params, $field, 200);
            if ($value !== '') $query->whereLike('i.' . $field, $this->like($value));
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (isset($params[$key]) && $params[$key] !== '') $query->where('i.price', $operator, Data::amount($params[$key]));
        }
        $page = max(1, (int) ($params['page'] ?? 1));
        $size = max(1, min(100, (int) ($params['limit'] ?? $params['page_size'] ?? 20)));
        $total = (int) (clone $query)->count();
        $rows = $query->field(self::INVENTORY_FIELDS)->order('i.id desc')->page($page, $size)->select()->toArray();
        return ['data' => array_map(fn(array $row): array => $this->presentInventory($row), $rows), 'total' => $total,
            'per_page' => $size, 'current_page' => $page, 'scope' => 'published_generations_only',
            'notice' => '仅展示各连接最近一次完整发布的库存；失败同步保留旧快照，列表存在不等于实物可售或已锁货'];
    }

    public function dashboard(): array
    {
        $this->context->manager();
        return [
            'merchants' => (int) Data::query('merchant', $this->context->site)->where('status', 1)->count(),
            'inventory' => (int) $this->inventoryQuery()->count(),
            'leads_pending' => (int) Data::query('lead', $this->context->site)->where('status', 'pending')->count(),
            'tasks_following' => (int) Data::query('task', $this->context->site)->where('status', 'following')->count(),
            'deals_pending' => (int) Data::query('task', $this->context->site)->where('status', 'deal_pending')->count(),
            'tasks_overdue' => (int) Data::query('task', $this->context->site)->where('status','assigned')->where('response_deadline','<',time())->count(),
            'tasks_partial' => (int) Data::query('task', $this->context->site)->where('status','following')->where('completion_status','partial')->count(),
            'commission_unpaid' => $this->commissionTotal('unpaid'),
            'commission_received' => $this->commissionTotal('paid'),
            'sync_failed' => (int) Data::query('connection', $this->context->site)->where('last_sync_status', 'failed')->count(),
            'recent_tasks' => $this->recentTasks(),
            'recent_syncs' => $this->recentSyncs(),
            'is_manager' => true,
        ];
    }

    /** Region/stock references for manual assignment, never a distance or stock reservation. */
    public function matches(int $leadId): array
    {
        $this->context->manager();
        $lead = Data::row('lead', $this->context->site, $leadId);
        $rawRequirements = trim((string) ($lead['requirements_json'] ?? ''));
        $requirements = [];
        foreach (Data::decode($rawRequirements) as $requirement) {
            if (!is_array($requirement) || !is_string($requirement['title'] ?? null) || trim($requirement['title']) === '') continue;
            $requirement['title'] = trim($requirement['title']);
            $requirement['quantity'] = max(1, (int) ($requirement['quantity'] ?? 1));
            $requirements[] = $requirement;
        }
        $structuredRequirements = $requirements !== [];
        // Existing single-model fixtures remain usable. A populated demand JSON never
        // falls back to the human-readable model summary (nor to malformed JSON).
        if ($requirements === [] && ($rawRequirements === '' || $rawRequirements === '[]')) {
            $legacyModel = trim((string) ($lead['model'] ?? ''));
            if ($legacyModel !== '') $requirements[] = ['title' => $legacyModel, 'quantity' => 1];
        }
        $demandMode = ($lead['demand_mode'] ?? 'any') === 'all' ? 'all' : 'any';
        $merchants = Data::query('merchant', $this->context->site)->where('status', 1)
            ->field('id,name,province_id,city_id,district_id,service_areas_json')->select()->toArray();
        $candidates = [];
        foreach ($merchants as $merchant) {
            $region = $this->matchRegion($merchant, $lead);
            if ($region === null) continue;
            $uniqueItems = [];
            $uniqueStaleItems = [];
            $matched = [];
            $unmatched = [];
            foreach ($requirements as $requirement) {
                $query = $this->inventoryQuery((int) $merchant['id'])->where('i.price', '>', '0.00');
                $variants = [$requirement['title']];
                foreach (is_array($requirement['aliases'] ?? null) ? $requirement['aliases'] : [] as $alias) {
                    if (is_string($alias) && trim($alias) !== '') $variants[] = trim($alias);
                }
                $variants = array_values(array_unique($variants));
                // Explicit title/aliases are OR alternatives of this one demand item;
                // words inside each variant are AND across real model/spec/color fields.
                // Source brand labels have no verified alias mapping, so title dominates.
                $query->where(function ($alternatives) use ($variants): void {
                    foreach ($variants as $variant) {
                        $tokens = preg_split('/\s+/u', $variant, -1, PREG_SPLIT_NO_EMPTY) ?: [$variant];
                        $alternatives->whereOr(function ($words) use ($tokens): void {
                            foreach ($tokens as $token) $words->whereLike('i.model|i.memory|i.color', $this->like($token));
                        });
                    }
                });
                foreach (['budget_min' => '>=', 'budget_max' => '<='] as $field => $operator) {
                    $budget = $requirement[$field] ?? null;
                    if ($budget === null || $budget === '') $budget = $lead[$field] ?? null;
                    if ($budget !== null && $budget !== '') $query->where('i.price', $operator, Data::amount($budget));
                }
                $referenceIds = [];
                foreach ($query->field(self::INVENTORY_FIELDS)->order('i.id desc')->select()->toArray() as $row) {
                    // Catalog demands need exact model identity after broad SQL recall.
                    // Legacy free-text fixtures retain their previous search semantics.
                    if ($structuredRequirements && !$this->exactDemandModel($variants, $row)) continue;
                    if (preg_match('/报废|内爆|故障|损坏|不开机/u', (string) $row['model'] . ' ' . (string) $row['grade'])) continue;
                    if (($row['availability'] ?? 'unknown') === 'unavailable') continue;
                    $item = $this->presentInventory($row);
                    if ($item['is_stale']) {
                        $uniqueStaleItems[$item['id']] = $item;
                    } else {
                        $referenceIds[$item['id']] = true;
                        $uniqueItems[$item['id']] = $item;
                    }
                }
                $result = ['title' => $requirement['title'], 'requested_quantity' => $requirement['quantity'],
                    'reference_count' => count($referenceIds), 'quantity_confirmed' => false];
                if ($referenceIds !== []) $matched[] = $result;
                else $unmatched[] = $result;
            }
            $count = count($uniqueItems);
            $staleCount = count($uniqueStaleItems);
            $needsManual = $demandMode === 'all' ? ($requirements === [] || $unmatched !== []) : $matched === [];
            $reason = $region['reason'] . ($count > 0 ? '；有型号及预算相符的新鲜库存资料，实物可售需商户确认' : '；未找到可用的新鲜匹配库存，须人工确认后派单');
            if ($demandMode === 'all' && $unmatched !== []) $reason .= '；全部需要模式仍有未匹配需求项，须人工核实缺口';
            elseif ($demandMode === 'any' && $matched !== [] && $unmatched !== []) $reason .= '；任选模式已匹配部分需求，其余缺口仍需确认';
            if ($requirements !== []) $reason .= '；参考条数不是可售数量，需求数量尚未确认';
            if ($staleCount > 0) $reason .= '；过期或同步失败快照仅供人工参考';
            $candidates[] = ['merchant_id' => (int) $merchant['id'], 'name' => (string) $merchant['name'],
                'match_level' => $region['level'], 'match_reason' => $reason, 'inventory_count' => $count,
                'items' => array_slice(array_values($uniqueItems), 0, 5), 'stale_inventory_count' => $staleCount,
                'stale_items' => array_slice(array_values($uniqueStaleItems), 0, 3), 'demand_mode' => $demandMode,
                'matched_requirements' => $matched, 'unmatched_requirements' => $unmatched,
                'quantity_confirmed' => false, 'needs_manual' => $needsManual, 'requires_stock_confirmation' => true, 'auto_assign_allowed' => false,
                '_rank' => $region['rank']];
        }
        usort($candidates, static fn(array $a, array $b): int => ($b['_rank'] <=> $a['_rank'])
            ?: ($b['inventory_count'] <=> $a['inventory_count']) ?: ($a['merchant_id'] <=> $b['merchant_id']));
        foreach ($candidates as &$candidate) unset($candidate['_rank']);
        unset($candidate);
        return ['lead_id' => $leadId, 'demand_mode' => $demandMode, 'candidates' => $candidates,
            'notices' => ['地区按明确登记的服务范围及门店省市区匹配，不估算地理距离', '库存资料不代表实物可售；派单不会自动锁货', '过期、失败同步、缺价格及故障机资料不计入有效库存候选',
                '按全部或任选需求逐项展示匹配与缺口；参考条数不等于可售数量，所有需求数量均需商户确认']];
    }

    public function portalStats(int $merchantId): array
    {
        $this->authorizeMerchant($merchantId);
        $connection = Data::query('connection', $this->context->site)->where('merchant_id', $merchantId)
            ->field('last_sync_at,last_sync_status,last_sync_message,published_run_id')->find();
        return ['inventory' => (int) $this->inventoryQuery($merchantId)->count(),
            'tasks_pending' => (int) Data::query('task', $this->context->site)->where('merchant_id', $merchantId)->where('status', 'assigned')->count(),
            'tasks_following' => (int) Data::query('task', $this->context->site)->where('merchant_id', $merchantId)->where('status', 'following')->count(),
            'deals_pending' => (int) Data::query('task', $this->context->site)->where('merchant_id', $merchantId)->where('status', 'deal_pending')->count(),
            'commission_unpaid' => $this->commissionTotal('unpaid', $merchantId),
            'commission_received' => $this->commissionTotal('paid', $merchantId),
            'last_sync_at' => (int) ($connection['last_sync_at'] ?? 0),
            'last_sync_status' => (string) ($connection['last_sync_status'] ?? 'idle'),
            'last_sync_message' => (string) ($connection['last_sync_message'] ?? '尚未同步'),
            'has_published_snapshot' => (int) ($connection['published_run_id'] ?? 0) > 0,
            'is_manager' => $this->context->uid > 0];
    }

    private function authorizeMerchant(int $merchantId): void
    {
        if ($merchantId <= 0) throw new CommonException('请提供有效商户');
        if ($this->context->uid > 0) {
            $this->context->manager();
            Data::row('merchant', $this->context->site, $merchantId);
        } elseif ((int) $this->context->merchant()['id'] !== $merchantId) {
            throw new CommonException('不能查看其他商户的库存或统计');
        }
    }

    private function inventoryQuery(?int $merchantId = null)
    {
        // The publication pointer is compared inside the same SQL statement; no row from
        // active_run_id or any other partially written generation can leak into this read.
        $query = Db::name('hsx_lead_inventory')->alias('i')
            ->join('hsx_lead_connection c', 'c.id=i.connection_id AND c.site_id=i.site_id AND c.merchant_id=i.merchant_id AND c.published_run_id=i.sync_run_id')
            ->join('hsx_lead_merchant m', 'm.id=i.merchant_id AND m.site_id=i.site_id')
            ->where('i.site_id', $this->context->site)->where('c.published_run_id', '>', 0);
        if ($merchantId !== null) $query->where('i.merchant_id', $merchantId);
        return $query;
    }

    private function presentInventory(array $row): array
    {
        $lastSync = (int) ($row['last_sync_at'] ?? 0);
        $fetched = (int) ($row['fetched_at'] ?? 0);
        $threshold = max(2 * max(1, (int) ($row['sync_interval_minutes'] ?? 60)), 120) * 60;
        $failed = ($row['last_sync_status'] ?? '') === 'failed';
        $timestamp = max($lastSync, $fetched);
        return ['id' => (int) $row['id'], 'merchant_id' => (int) $row['merchant_id'],
            'merchant_name' => (string) ($row['merchant_name'] ?? ''), 'model' => (string) ($row['model'] ?? ''),
            'memory' => (string) ($row['memory'] ?? ''), 'brand' => (string) ($row['brand'] ?? ''),
            'color' => (string) ($row['color'] ?? ''), 'grade' => (string) ($row['grade'] ?? ''),
            'price' => $row['price'] === null ? null : (string) $row['price'],
            'trade_price' => $row['trade_price'] === null ? null : (string) $row['trade_price'],
            'quantity' => $row['quantity'] === null ? null : (string) $row['quantity'],
            'imei' => (string) ($row['imei'] ?? ''), 'cover' => (string) ($row['cover'] ?? ''),
            'availability' => 'unknown', 'is_stale' => $failed || $timestamp <= 0 || $timestamp + $threshold < time(),
            'last_sync_at' => $lastSync, 'fetched_at' => $fetched, 'stale_after_minutes' => intdiv($threshold, 60),
            'sync_failed' => $failed, 'last_sync_status' => (string) ($row['last_sync_status'] ?? 'idle'),
            'provider' => (string) ($row['provider'] ?? ''), 'sync_run_id' => (int) ($row['sync_run_id'] ?? 0)];
    }

    private function like(string $value): string
    {
        return '%' . addcslashes($value, '\\%_') . '%';
    }

    /** No model dictionary: an extra source-model suffix is never a base-model match. */
    private function exactDemandModel(array $variants, array $row): bool
    {
        $normalize = static fn(string $value): string => preg_replace('/[\s\p{P}\p{Z}]+/u', '', mb_strtolower($value)) ?? '';
        $brand = $normalize((string) ($row['brand'] ?? ''));
        $stripBrand = static fn(string $value): string => $brand !== '' && str_starts_with($value, $brand) ? substr($value, strlen($brand)) : $value;
        $model = $stripBrand($normalize((string) ($row['model'] ?? '')));
        if ($model === '') return false;
        // Convert only explicit capacity unit spellings, not model-name characters.
        $capacity = static fn(string $value): string => preg_replace('/(?<=\d)(g|t)b/u', '$1', $value) ?? '';
        $memory = $capacity($normalize((string) ($row['memory'] ?? '')));
        $color = $normalize((string) ($row['color'] ?? ''));
        $memories = $memory === '' ? [] : [$memory];
        foreach (explode('+', $memory) as $part) {
            if (preg_match('/^[0-9]+(?:\.[0-9]+)?[gmt]$/D', $part)) $memories[] = $part;
        }
        $colors = $color === '' ? [] : [$color];
        if (str_ends_with($color, '色') && mb_strlen($color) > 1) $colors[] = mb_substr($color, 0, -1);
        $suffixes = array_merge($memories, $colors);
        foreach ($memories as $memoryPart) foreach ($colors as $colorPart) {
            $suffixes[] = $memoryPart . $colorPart;
            $suffixes[] = $colorPart . $memoryPart;
        }
        foreach ($variants as $variant) {
            $demand = $stripBrand($normalize($variant));
            if ($demand === $model) return true;
            // Only a demand may have extra text, and all of it must be explained by
            // this row's real memory/color fields. "Pro", "Max", "mini" in the
            // source model can therefore never be swallowed by a broader demand.
            if (str_starts_with($demand, $model) && in_array($capacity(substr($demand, strlen($model))), $suffixes, true)) return true;
        }
        return false;
    }

    private function matchRegion(array $merchant, array $lead): ?array
    {
        $province = (int) ($lead['province_id'] ?? 0);
        $city = (int) ($lead['city_id'] ?? 0);
        $district = (int) ($lead['district_id'] ?? 0);
        if ($province <= 0 || $city <= 0 || $district <= 0) return null;
        $rawAreas = trim((string) ($merchant['service_areas_json'] ?? ''));
        $areas = Data::decode($rawAreas);
        // An explicit but malformed scope must not silently fall back to home coverage.
        if ($areas === [] && $rawAreas !== '' && $rawAreas !== '[]') return null;
        if ($areas !== []) {
            $rank = 0;
            foreach ($areas as $area) {
                if (!is_array($area) || (int) ($area['province_id'] ?? 0) !== $province) continue;
                $areaCity = (int) ($area['city_id'] ?? 0);
                $areaDistrict = (int) ($area['district_id'] ?? 0);
                if ($areaCity < 0 || $areaDistrict < 0 || ($areaDistrict > 0 && $areaCity === 0)) continue;
                if (($areaCity > 0 && $areaCity !== $city) || ($areaDistrict > 0 && $areaDistrict !== $district)) continue;
                $rank = max($rank, $areaDistrict > 0 ? 3 : ($areaCity > 0 ? 2 : 1));
            }
            if ($rank === 0) return null;
            $reason = '显式服务范围覆盖目标' . [1 => '省份', 2 => '城市', 3 => '区县'][$rank];
            // Explicit coverage determines eligibility first. Within that scope, retain
            // home district/city/province priority; never turn it into a distance estimate.
            if ((int) ($merchant['province_id'] ?? 0) === $province) {
                $rank = (int) ($merchant['city_id'] ?? 0) === $city ? ((int) ($merchant['district_id'] ?? 0) === $district ? 3 : 2) : 1;
                $reason .= '；' . [1 => '门店所在地与客户同省', 2 => '门店所在地与客户同城', 3 => '门店所在地与客户同区县'][$rank];
            }
            $level = [1 => 'province', 2 => 'city', 3 => 'district'][$rank];
            return ['level' => $level, 'rank' => $rank, 'reason' => $reason];
        }
        if ((int) ($merchant['province_id'] ?? 0) !== $province) return null;
        $rank = (int) ($merchant['city_id'] ?? 0) === $city ? ((int) ($merchant['district_id'] ?? 0) === $district ? 3 : 2) : 1;
        return ['level' => [1 => 'province', 2 => 'city', 3 => 'district'][$rank], 'rank' => $rank,
            'reason' => [1 => '门店所在地与客户同省，作为备选', 2 => '门店所在地与客户同城，作为备选', 3 => '门店所在地与客户同区县，优先参考'][$rank]];
    }

    private function commissionTotal(string $status, ?int $merchantId = null): string
    {
        $query = Data::query('commission', $this->context->site)->where('status', $status);
        if ($merchantId !== null) $query->where('merchant_id', $merchantId);
        // ThinkORM sum() returns float; use a DECIMAL aggregate row instead.
        $row = $query->fieldRaw('COALESCE(SUM(amount), 0) AS total')->find();
        return bcadd((string) ($row['total'] ?? '0'), '0', 2);
    }

    private function recentTasks(): array
    {
        $rows = Data::query('task', $this->context->site)->field('id,task_no,lead_id,merchant_id,status,response_deadline,create_time,update_time')
            ->order('id desc')->limit(8)->select()->toArray();
        foreach ($rows as &$row) {
            $row['merchant_name'] = (string) (Data::query('merchant', $this->context->site)->where('id', $row['merchant_id'])->value('name') ?? '');
            $lead = Data::query('lead', $this->context->site)->where('id', $row['lead_id'])->field('customer_name,model')->find();
            $row['customer_name'] = (string) ($lead['customer_name'] ?? '');
            $row['model'] = (string) ($lead['model'] ?? '');
        }
        unset($row);
        return $rows;
    }

    private function recentSyncs(): array
    {
        $rows = Data::query('sync_run', $this->context->site)
            ->field('id,merchant_id,connection_id,status,page_count,record_count,started_at,finished_at,error_code,error_message,create_time')
            ->order('id desc')->limit(8)->select()->toArray();
        foreach ($rows as &$row) $row['merchant_name'] = (string) (Data::query('merchant', $this->context->site)->where('id', $row['merchant_id'])->value('name') ?? '');
        unset($row);
        return $rows;
    }
}
