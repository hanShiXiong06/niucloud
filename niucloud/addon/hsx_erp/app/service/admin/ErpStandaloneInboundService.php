<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpCounterparty;
use addon\hsx_erp\app\model\FinancePayable;
use addon\hsx_erp\app\model\FinanceReceivable;
use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\service\admin\ErpAssetService;
use addon\hsx_erp\app\service\admin\FinanceCounterpartyBalanceService;
use addon\hsx_erp\app\service\admin\FinanceSettlementService;
use addon\hsx_erp\app\service\core\ErpInboundService;
use addon\hsx_erp\app\support\ErpMoney;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * ERP 独立建档入口。
 *
 * 这里只负责将后台录入转换为标准入库事件，真正的资产、周期和入库单
 * 仍统一由 ErpInboundService 创建。
 */
class ErpStandaloneInboundService extends BaseAdminService
{
    public function create(array $data): array
    {
        $imei = trim((string)($data['imei'] ?? ''));
        $sn = trim((string)($data['sn'] ?? ''));
        $model = trim((string)($data['model'] ?? ''));
        if ($imei === '' && $sn === '') {
            throw new CommonException('IMEI 和 SN 至少填写一个');
        }
        if ($model === '') {
            throw new CommonException('请填写设备型号');
        }

        $businessType = (string)($data['business_type'] ?? 'recycle');
        if (!in_array($businessType, ['recycle', 'purchase', 'consignment', 'opening'], true)) {
            throw new CommonException('入库业务类型不正确');
        }
        $counterpartyId = (int)($data['counterparty_id'] ?? 0);
        if ($businessType !== 'opening' && $counterpartyId <= 0) {
            throw new CommonException('请选择往来单位');
        }
        // 入库仓库/库位：选了即在建档后自动确认入库到该库位（不悬"待入库"）。
        // 选了仓库就必须选库位（确认入库强制要库位）。
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $locationId = (int)($data['location_id'] ?? 0);
        if ($warehouseId > 0 && $locationId <= 0) {
            throw new CommonException('选择了入库仓库，请同时选择库位');
        }
        $needRefurb = !empty($data['need_refurb']);
        $purchaseCost = ErpMoney::normalize($data['purchase_cost'] ?? 0);
        $paidAmount = ErpMoney::normalize($data['paid_amount'] ?? 0);
        if (in_array($businessType, ['consignment', 'opening'], true)) {
            $paidAmount = '0.00';
        }
        if ($businessType === 'consignment') {
            $purchaseCost = '0.00';
        }
        if (ErpMoney::compare($paidAmount, $purchaseCost) > 0) {
            throw new CommonException('已付金额不能大于应付金额');
        }
        $payableAmount = in_array($businessType, ['recycle', 'purchase'], true) ? $purchaseCost : '0.00';
        $settlementStatus = ErpMoney::compare($payableAmount, '0.00') === 0
            ? 'not_applicable'
            : (ErpMoney::compare($paidAmount, '0.00') === 0
                ? 'unpaid'
                : (ErpMoney::compare($paidAmount, $payableAmount) >= 0 ? 'paid' : 'partial'));

        // 已付>0 必须选付款户头, 且建档前先校验余额(不够直接拦, 不创建资产)
        $paidAccountId = (int)($data['paid_account_id'] ?? 0);
        if (in_array($businessType, ['recycle', 'purchase'], true) && ErpMoney::compare($paidAmount, '0.00') > 0) {
            if ($paidAccountId <= 0) {
                throw new CommonException('填写了已付金额，请选择付款户头');
            }
            $payAcc = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $paidAccountId]])->findOrEmpty();
            if ($payAcc->isEmpty()) {
                throw new CommonException('付款户头不存在');
            }
            if (round((float)$payAcc->balance, 2) < round((float)$paidAmount, 2)) {
                throw new CommonException(sprintf('账户「%s」余额不足：当前 %.2f，需付出 %.2f，请改用其他户头', (string)$payAcc->account_name, (float)$payAcc->balance, (float)$paidAmount));
            }
        }

        $sourceDeviceId = $this->makeSourceDeviceId();
        $now = time();
        $event = [
            'event_id' => 'erp-manual-inbound-' . $this->site_id . '-' . date('YmdHis') . '-' . random_int(100000, 999999),
            'event_name' => 'erp.manual.inbound_requested',
            'event_version' => 1,
            'site_id' => $this->site_id,
            'occurred_at' => $now,
            'targets' => ['self_erp'],
            'operator' => [
                'type' => 'staff',
                'id' => $this->uid,
                'name' => $this->username ?: '',
            ],
            'source' => [
                'plugin' => 'hsx_erp',
                'type' => 'manual_inbound',
                'id' => $sourceDeviceId,
            ],
            'remark' => trim((string)($data['remark'] ?? '')),
            'devices' => [[
                'source_id' => $sourceDeviceId,
                'source_device_id' => $sourceDeviceId,
                'imei' => $imei,
                'imei2' => trim((string)($data['imei2'] ?? '')),
                'sn' => $sn,
                'model' => $model,
                'category_id' => (int)($data['category_id'] ?? 0),
                'capacity' => trim((string)($data['capacity'] ?? '')),
                'color' => trim((string)($data['color'] ?? '')),
                'business_type' => $businessType,
                'ownership_type' => $businessType === 'consignment' ? 'consign' : 'owned',
                'counterparty' => $counterpartyId > 0 ? ['id' => $counterpartyId] : [],
                'purchase_cost' => $purchaseCost,
                'payable_amount' => $payableAmount,
                'paid_amount' => $paidAmount,
                'settlement_status' => $settlementStatus,
                'suggested_sale_price' => round((float)($data['suggested_sale_price'] ?? 0), 2),
                'acquired_at' => $now,
                'check_snapshot' => [],
                'sales_pricing_snapshot' => [
                    'purchase_cost' => $purchaseCost,
                    'suggested_sale_price' => round((float)($data['suggested_sale_price'] ?? 0), 2),
                ],
                'pricing_snapshot' => [
                    'pricing_type' => 'sales',
                    'purchase_cost' => $purchaseCost,
                    'suggested_sale_price' => round((float)($data['suggested_sale_price'] ?? 0), 2),
                ],
                'refurbishment' => [
                    'required' => $needRefurb,
                    'decision_source' => 'manual',
                    'reason' => $needRefurb ? '建档时标记需要整备' : '建档时标记无需整备',
                    'suggested_items' => [],
                    'estimated_cost' => '0.00',
                    'decided_by' => [
                        'type' => 'staff',
                        'id' => $this->uid,
                        'name' => $this->username ?: '',
                    ],
                    'decided_at' => $now,
                ],
                'manual_input' => [
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'remark' => trim((string)($data['remark'] ?? '')),
                ],
            ]],
        ];

        $result = (new ErpInboundService())->receive($event);

        // 选了仓库+库位 → 建档即自动确认入库到该库位；随后由"无需整备"决策按是否带售价转「可售」或「待定价」。
        // 故障隔离：确认失败仅记日志，资产留在"待入库"由人工确认，不影响建档主流程。
        if ($warehouseId > 0 && $locationId > 0) {
            $created = (array)($result['created_assets'] ?? []);
            foreach ($created as $createdAsset) {
                $assetId = (int)($createdAsset['id'] ?? 0);
                if ($assetId <= 0) {
                    continue;
                }
                try {
                    (new ErpAssetService())->confirmInboundByAsset($assetId, [
                        'warehouse_id' => $warehouseId,
                        'location_id' => $locationId,
                        'remark' => '手工建档入库，确认入库到指定库位',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('手工建档自动确认入库失败：' . $e->getMessage(), [
                        'site_id' => $this->site_id,
                        'asset_id' => $assetId,
                    ]);
                }
            }
        }

        // 接入财务: 建档生成"应付"(全额, 锚主体), 已付部分当场从户头结清。
        // 故障隔离: 发应付/结算失败只记日志, 不影响建档(余额已在建档前预检)。
        if (in_array($businessType, ['recycle', 'purchase'], true) && ErpMoney::compare($payableAmount, '0.00') > 0) {
            try {
                $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['source_device_id', '=', $sourceDeviceId]])->order('id desc')->findOrEmpty();
                $assetId = (int)($asset->id ?? 0);
                // 财务应付锚定到「对接人(member_id)」, 与回收/出库口径一致, 才能按主体正确归账、跨人折账。
                // 拿不到 member_id 时退回主体PK(至少能查到账, 仅可能落到"未归属")。
                $memberId = (int)($data['counterparty_member_id'] ?? 0);
                $entityId = (int)($asset->counterparty_id ?? 0) ?: $counterpartyId;
                $cpId = $memberId > 0 ? $memberId : $entityId;
                if ($assetId > 0 && $cpId > 0) {
                    if ($memberId > 0) {
                        $mm = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, [$memberId]);
                        $cpName = (string)($mm[$memberId]['name'] ?? '');
                    } else {
                        $cpName = (string)(ErpCounterparty::where([['site_id', '=', $this->site_id], ['id', '=', $cpId]])->value('name') ?: '');
                    }
                    $payableNo = 'ERPIN' . $assetId;
                    event('FinancePayableCreated', [
                        'event'             => 'finance.payable.created.v1',
                        'event_id'          => 'erp_inbound_payable_' . $sourceDeviceId,
                        'site_id'           => (int)$this->site_id,
                        'counterparty_id'   => $cpId,
                        'counterparty_name' => $cpName,
                        'amount'            => round((float)$payableAmount, 2),
                        'source_type'       => 'erp_inbound',
                        'source_no'         => $payableNo,
                        'source_device_id'  => $sourceDeviceId,
                        'occurred_at'       => $now,
                        'remark'            => '手工建档入库应付',
                    ]);

                    // 找到刚生成的应付，按选择处理：用预付折账 / 现金已付 / 都不(挂应付)
                    $payable = FinancePayable::where([
                        ['site_id', '=', $this->site_id],
                        ['source_device_id', '=', $sourceDeviceId],
                        ['source_type', '=', 'erp_inbound'],
                    ])->order('id desc')->findOrEmpty();
                    if (!$payable->isEmpty()) {
                        if (!empty($data['use_prepay']) && $memberId > 0 && (int)$payable->counterparty_id === $memberId) {
                            // 用该供应商未结"采购预付"应收折账核销本台应付(不再走现金)；
                            // settlement_link 会记录"这台设备的应付 ↔ 哪笔预付"，预付花在哪台可追溯。
                            $prepayIds = FinanceReceivable::where([
                                ['site_id', '=', $this->site_id],
                                ['counterparty_id', '=', $memberId],
                                ['source_type', '=', 'prepay'],
                                ['status', 'in', [FinanceDict::STATUS_PENDING, FinanceDict::STATUS_PARTIAL]],
                            ])->order('occurred_at asc')->column('id');
                            if (!empty($prepayIds)) {
                                (new FinanceSettlementService())->settle(
                                    $memberId,
                                    [(int)$payable->id],
                                    array_map('intval', $prepayIds),
                                    ['record_cash' => false, 'remark' => '入库核销采购预付（' . $payableNo . '）']
                                );
                            }
                        } elseif ($paidAccountId > 0 && ErpMoney::compare($paidAmount, '0.00') > 0) {
                            // 已付>0: 从户头当场结清这部分(部分=订金, 全额=结清)
                            (new FinanceSettlementService())->payCashByPayable((int)$payable->id, (float)$paidAmount, $paidAccountId, ['remark' => '入库已付（' . $payableNo . '）']);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[erp] 入库接入财务失败：' . $e->getMessage(), [
                    'site_id' => $this->site_id,
                    'source_device_id' => $sourceDeviceId,
                ]);
            }
        }

        return $result;
    }

    private function makeSourceDeviceId(): int
    {
        do {
            $id = random_int(100000000, 2147483647);
            $exists = ErpAssetCycle::where([
                ['site_id', '=', $this->site_id],
                ['source_plugin', '=', 'hsx_erp'],
                ['source_type', '=', 'manual_inbound'],
                ['source_device_id', '=', $id],
            ])->count();
        } while ($exists > 0);

        return $id;
    }
}
