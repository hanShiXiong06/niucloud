<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\job\PublishOutboxEvent;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpPriceLog;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\support\ErpDomainEvent;
use addon\hsx_erp\app\support\ErpMoney;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpPricingService extends BaseAdminService
{
    public function getPage(array $where = []): array
    {
        $query = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('inventory_status', [
                ErpDict::INVENTORY_PENDING_PRICING,
                ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
            ])
            // 回收来源设备不在 ERP 定价：商城销路去中台拍照定价，非商城销路在回收定价时已定。
            // 故 ERP 销售定价 list 只面向 ERP 自建档(手工入库)等非回收资产。
            ->where('cycle_id', 'not in', function ($sub) {
                $sub->name('erp_asset_cycle')
                    ->where('site_id', $this->site_id)
                    ->where('source_plugin', 'hsx_recycle')
                    ->field('id');
            })
            ->order('id desc');

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('source_device_id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        if (!empty($where['inventory_status'])) {
            $query->where('inventory_status', '=', (string)$where['inventory_status']);
        }
        if (!empty($where['asset_id'])) {
            $query->where('id', '=', (int)$where['asset_id']);
        }

        $page = $this->pageQuery($query);
        $assetIds = array_map('intval', array_column($page['data'] ?? [], 'id'));
        if (!empty($assetIds)) {
            $logs = ErpPriceLog::where([['site_id', '=', $this->site_id]])
                ->whereIn('asset_id', $assetIds)
                ->order('id desc')->select()->toArray();
            $logMap = [];
            foreach ($logs as $log) {
                $assetId = (int)$log['asset_id'];
                if (!isset($logMap[$assetId])) {
                    $logMap[$assetId] = $log;
                }
            }
            foreach ($page['data'] as &$asset) {
                $asset['latest_price_log'] = $logMap[(int)$asset['id']] ?? null;
                $asset['gross_profit'] = ErpMoney::subtract($asset['current_sale_price'] ?? 0, $asset['current_cost'] ?? 0);
            }
            unset($asset);
        }

        return $page;
    }

    public function getInfo(int $assetId): array
    {
        $asset = $this->findAsset($assetId);
        return [
            'asset' => $asset->toArray(),
            'price_logs' => ErpPriceLog::where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
            ])->order('id desc')->select()->toArray(),
        ];
    }

    public function price(int $assetId, array $data): array
    {
        $salePrice = ErpMoney::normalize($data['sale_price'] ?? '');
        $minProfit = ErpMoney::normalize($data['min_profit'] ?? 0);
        $remark = trim((string)($data['remark'] ?? ''));
        if (ErpMoney::compare($salePrice, '0.00') <= 0) {
            throw new CommonException('销售价必须大于 0');
        }
        if (ErpMoney::compare($minProfit, '0.00') < 0) {
            throw new CommonException('最低利润不能为负数');
        }

        $now = time();
        $outboxId = 0;
        Db::startTrans();
        try {
            $asset = $this->findAsset($assetId, true);
            $status = (string)$asset->inventory_status;
            if (!in_array($status, [ErpDict::INVENTORY_PENDING_PRICING, ErpDict::INVENTORY_AVAILABLE_FOR_SALE], true)) {
                throw new CommonException('只有待销售定价或可售设备可以定价');
            }
            $beforePrice = ErpMoney::normalize($asset->current_sale_price);
            $currentCost = ErpMoney::normalize($asset->current_cost);
            $grossProfit = ErpMoney::subtract($salePrice, $currentCost);
            if (ErpMoney::compare($grossProfit, $minProfit) < 0) {
                throw new CommonException('销售价低于最低利润要求');
            }

            $action = $status === ErpDict::INVENTORY_PENDING_PRICING
                ? ErpDict::PRICE_ACTION_INITIAL
                : ErpDict::PRICE_ACTION_ADJUST;
            $asset->save([
                'inventory_status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                'current_sale_price' => $salePrice,
                'version' => (int)$asset->version + 1,
                'update_at' => $now,
            ]);
            ErpAssetCycle::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$asset->cycle_id],
            ])->update([
                'status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                'update_at' => $now,
            ]);

            $grossMargin = ErpMoney::compare($salePrice, '0.00') > 0
                ? round(((float)$grossProfit / (float)$salePrice) * 100, 4)
                : 0;
            ErpPriceLog::create([
                'site_id' => $this->site_id,
                'price_no' => $this->makeNo('PL'),
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'action' => $action,
                'before_price' => $beforePrice,
                'after_price' => $salePrice,
                'current_cost' => $currentCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $grossMargin,
                'min_profit' => $minProfit,
                'operator_id' => $this->uid,
                'operator_name' => $this->username ?: '',
                'remark' => $remark,
                'occurred_at' => $now,
            ]);

            if ($status !== ErpDict::INVENTORY_AVAILABLE_FOR_SALE) {
                ErpStockLedger::create([
                    'site_id' => $this->site_id,
                    'ledger_no' => $this->makeNo('SL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'stock_order_id' => 0,
                    'action' => 'sales_priced',
                    'before_status' => $status,
                    'after_status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                    'warehouse_id' => (int)$asset->warehouse_id,
                    'location_id' => (int)$asset->location_id,
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'occurred_at' => $now,
                    'payload' => ['sale_price' => $salePrice, 'remark' => $remark],
                ]);
            }
            ErpOperationEvent::create([
                'site_id' => $this->site_id,
                'event_id' => $this->makeEventId('priced', (int)$asset->id),
                'event_name' => 'erp.asset.priced.v1',
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'document_type' => 'pricing',
                'document_id' => (int)$asset->id,
                'stage' => 'sales_pricing',
                'action' => $action === ErpDict::PRICE_ACTION_INITIAL ? 'initial_price' : 'adjust_price',
                'operator_id' => $this->uid,
                'operator_name' => $this->username ?: '',
                'payload' => [
                    'before_price' => $beforePrice,
                    'after_price' => $salePrice,
                    'current_cost' => $currentCost,
                    'gross_profit' => $grossProfit,
                    'min_profit' => $minProfit,
                ],
                'occurred_at' => $now,
            ]);
            $event = ErpDomainEvent::create(
                $this->site_id,
                'erp.asset.priced.v1',
                $this->makeEventId('priced-outbox', (int)$asset->id),
                'asset',
                (int)$asset->id,
                ['type' => 'staff', 'id' => $this->uid, 'name' => $this->username ?: ''],
                ['plugin' => 'hsx_erp', 'type' => 'pricing', 'id' => (int)$asset->id],
                [
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'sale_price' => $salePrice,
                    'current_cost' => $currentCost,
                    'gross_profit' => $grossProfit,
                    'inventory_status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                ],
                $now
            );
            $outbox = ErpDomainEvent::writeOutbox($event, $now);
            $outboxId = (int)$outbox->id;
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

        // 正规路打通商城 + 调价回流:这台走过拍照(有图片=三要素齐图片+价格+质检)→ 定价/调价即发出口事件,
        // 落商城「待上架货源」并按 erp_asset_id 幂等更新(再次定价=改价回流)。故障隔离,不影响定价主流程。
        try {
            $snapshot = (array)$asset->source_snapshot;
            $images = array_values(array_filter(array_map('strval', (array)($snapshot['images'] ?? [])), static fn($u) => trim($u) !== ''));
            if (!empty($images)) {
                event('DeviceAssetPriceCompleted', [
                    'event_name' => 'device_asset.price.completed.v1',
                    'site_id'    => (int)$this->site_id,
                    'asset_id'   => 0,
                    'device_id'  => 0,
                    'operator'   => ['id' => $this->uid, 'name' => $this->username ?: ''],
                    'payload'    => [
                        'erp_asset_id' => (int)$asset->id,
                        'site_id'      => (int)$this->site_id,
                        'sale_price'   => (float)$salePrice,
                        'peer_price'   => 0,
                        'min_price'    => (float)$minProfit,
                        'cost_price'   => (float)$currentCost,
                        'remark'       => $remark,
                        'model_name'   => (string)$asset->model,
                        'memory'       => (string)$asset->capacity,
                        'color'        => (string)$asset->color,
                        'imei'         => (string)$asset->imei,
                        'qc_info'      => (array)$asset->check_snapshot,
                        'images'       => $images,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            \think\facade\Log::warning('[erp] 定价后推商城货源失败：' . $e->getMessage());
        }

        PublishOutboxEvent::dispatch(['outbox_id' => $outboxId]);
        return $this->getInfo($assetId);
    }

    private function findAsset(int $assetId, bool $lock = false): ErpAsset
    {
        $query = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ]);
        if ($lock) {
            $query->lock(true);
        }
        $asset = $query->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('ERP资产不存在');
        }
        return $asset;
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT)
            . random_int(100000, 999999);
    }

    private function makeEventId(string $type, int $assetId): string
    {
        return 'erp-' . $type . '-' . $this->site_id . '-' . $assetId . '-' . time() . '-' . random_int(1000, 9999);
    }
}
