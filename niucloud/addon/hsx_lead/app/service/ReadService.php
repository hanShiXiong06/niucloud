<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\support\ListTimeFilter;
use addon\hsx_lead\app\support\MerchantRegion;
use addon\hsx_lead\app\support\InventoryAvailability;
use core\exception\CommonException;
use think\facade\Db;

/** Safe read models. A staged or failed generation is never exposed as current stock. */
final class ReadService
{
    private const INVENTORY_FIELDS = 'i.id,i.merchant_id,i.connection_id,i.sync_run_id,i.source_id,i.source_store_id,i.tenant_id,'
        . 'i.model,i.memory,i.brand,i.color,i.grade,i.price,i.trade_price,i.quantity,i.saleable_quantity,i.imei,i.cover,i.availability,i.source_status_json,i.fetched_at,'
        . 'm.name AS merchant_name,m.status AS merchant_status,m.province_id,m.city_id,m.district_id,'
        . 'mp.name AS province_name,mc.name AS city_name,md.name AS district_name,'
        . 'c.provider,c.last_sync_at,c.last_sync_status,c.sync_interval_minutes';

    public function __construct(private Context $context) {}

    public function inventory(array $params = [], ?int $merchantId = null): array
    {
        if ($merchantId === null) {
            $this->context->manager();
            $filter = (int) ($params['merchant_id'] ?? 0);
            $merchantId = $filter > 0 ? $filter : null;
        } else {
            $this->authorizeMerchant($merchantId);
        }
        $scope=InventoryAvailability::scope($params,$this->context->uid>0);
        $query = $this->inventoryQuery($merchantId,'all');
        MerchantRegion::apply($query, $params, 'm.');
        $keyword = Data::text($params, 'keyword', 200);
        if ($keyword !== '') $query->whereLike('i.model|i.memory|i.brand|i.color|i.imei', $this->like($keyword));
        foreach (['model', 'brand', 'memory', 'color'] as $field) {
            $value = Data::text($params, $field, 200);
            if ($value !== '') $query->whereLike('i.' . $field, $this->like($value));
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (isset($params[$key]) && $params[$key] !== '') $query->where('i.price', $operator, Data::amount($params[$key]));
        }
        ListTimeFilter::apply($query, $params, ['fetched_at' => 'i.fetched_at', 'last_sync_at' => 'c.last_sync_at'], 'fetched_at');
        InventoryAvailability::apply($query,$scope);
        $page = max(1, (int) ($params['page'] ?? 1));
        $size = max(1, min(100, (int) ($params['limit'] ?? $params['page_size'] ?? 20)));
        $total = (int) (clone $query)->count();
        $rows = $query->field(self::INVENTORY_FIELDS)->order('i.id desc')->page($page, $size)->select()->toArray();
        return ['data' => array_map(fn(array $row): array => $this->presentInventory($row), $rows), 'total' => $total,
            'per_page' => $size, 'current_page' => $page, 'scope' => 'published_generations_only',
            'inventory_scope'=>$scope,
            'notice' => '默认只展示来源已确认在售且有货的有效快照；下架、无货、状态未核实及过期资料不参与匹配。来源未再返回的设备随完整同步退出当前清单，不能据此认定已成交；失败或未完成同步不发布新库存'];
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

    private function inventoryQuery(?int $merchantId = null, string $scope='sellable')
    {
        // The publication pointer is compared inside the same SQL statement; no row from
        // active_run_id or any other partially written generation can leak into this read.
        $query = Db::name('hsx_lead_inventory')->alias('i')
            ->join('hsx_lead_connection c', 'c.id=i.connection_id AND c.site_id=i.site_id AND c.merchant_id=i.merchant_id AND c.published_run_id=i.sync_run_id')
            ->join('hsx_lead_merchant m', 'm.id=i.merchant_id AND m.site_id=i.site_id')
            ->leftJoin('sys_area mp', 'mp.id=m.province_id')
            ->leftJoin('sys_area mc', 'mc.id=m.city_id')
            ->leftJoin('sys_area md', 'md.id=m.district_id')
            ->where('i.site_id', $this->context->site)->where('c.published_run_id', '>', 0);
        if ($merchantId !== null) $query->where('i.merchant_id', $merchantId);
        return InventoryAvailability::apply($query,$scope);
    }

    private function presentInventory(array $row): array
    {
        $lastSync = (int) ($row['last_sync_at'] ?? 0);
        $fetched = (int) ($row['fetched_at'] ?? 0);
        $province = (string)($row['province_name'] ?? '');
        $city = (string)($row['city_name'] ?? '');
        $district = (string)($row['district_name'] ?? '');
        return array_replace(['id' => (int) $row['id'], 'merchant_id' => (int) $row['merchant_id'],
            'merchant_name' => (string) ($row['merchant_name'] ?? ''), 'model' => (string) ($row['model'] ?? ''),
            'province_id' => (int)($row['province_id'] ?? 0), 'city_id' => (int)($row['city_id'] ?? 0),
            'district_id' => (int)($row['district_id'] ?? 0), 'province_name' => $province,
            'city_name' => $city, 'district_name' => $district,
            'region_text' => implode(' / ', array_values(array_filter([$province, $city, $district], static fn(string $name): bool => $name !== ''))),
            'memory' => (string) ($row['memory'] ?? ''), 'brand' => (string) ($row['brand'] ?? ''),
            'color' => (string) ($row['color'] ?? ''), 'grade' => (string) ($row['grade'] ?? ''),
            'price' => $row['price'] === null ? null : (string) $row['price'],
            'trade_price' => $row['trade_price'] === null ? null : (string) $row['trade_price'],
            'imei' => (string) ($row['imei'] ?? ''), 'cover' => (string) ($row['cover'] ?? ''),
            'last_sync_at' => $lastSync, 'fetched_at' => $fetched,
            'last_sync_status' => (string) ($row['last_sync_status'] ?? 'idle'),
            'provider' => (string) ($row['provider'] ?? ''), 'sync_run_id' => (int) ($row['sync_run_id'] ?? 0)]
            ,InventoryAvailability::present($row));
    }

    private function like(string $value): string
    {
        return '%' . addcslashes($value, '\\%_') . '%';
    }

}
