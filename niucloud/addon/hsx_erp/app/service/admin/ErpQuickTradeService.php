<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpOutboundItem;
use addon\hsx_erp\app\dict\ErpDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 快速出入库（一单成账）。
 *
 * 业务诉求：有时只是"走个账"——某台机器在我账上留一笔。快速选好供货人、买家,
 * 写好进价(供货价)、出货价,一行一台,多台也行,只留关键信息(型号/IMEI)。
 *
 * 实现：每台设备复用三段成熟逻辑,确保账货一致、可追溯,不重复造轮子：
 *   1) 入库(business_type=purchase, 供货人, 进价, 未付)  → 建最小资产 + 生成「应付供货人」(挂账)
 *   2) 资产翻为可出库(in_stock)                          → 跳过仓库/拍照,过账不关心库位
 *   3) 出库(挂账, 买家) + 回填价                          → 生成「应收买家」(挂账)
 *
 * 默认两笔都「挂账」(应付/应收均为未结),后续走正常核销收/付款。逐行容错,返回成功/失败汇总。
 */
class ErpQuickTradeService extends BaseAdminService
{
    /**
     * @param array $data [
     *   supplier_id(对接人 counterparty_id), supplier_member_id(member_id),
     *   buyer_id(买家 member_id),
     *   remark,
     *   items => [ {model, imei, sn, remark, purchase_cost, sale_price}, ... ]
     * ]
     * @return array 汇总
     */
    public function create(array $data): array
    {
        $supplierCpId     = (int)($data['supplier_id'] ?? 0);
        $supplierMemberId = (int)($data['supplier_member_id'] ?? 0);
        $buyerMemberId    = (int)($data['buyer_id'] ?? 0);
        $batchRemark      = trim((string)($data['remark'] ?? ''));
        $rows             = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($supplierCpId <= 0) {
            throw new CommonException('请选择供货人（向谁进的货）');
        }
        if ($buyerMemberId <= 0) {
            throw new CommonException('请选择买家（卖给了谁）');
        }
        $rows = array_values(array_filter($rows, static function ($r) {
            return is_array($r) && (trim((string)($r['model'] ?? '')) !== '');
        }));
        if (empty($rows)) {
            throw new CommonException('请至少录入一台设备（型号必填）');
        }
        // 预校验每行关键信息,避免半批写入
        foreach ($rows as $i => $r) {
            $n = $i + 1;
            if (trim((string)($r['imei'] ?? '')) === '' && trim((string)($r['sn'] ?? '')) === '') {
                throw new CommonException("第{$n}台：IMEI 和 SN 至少填一个");
            }
            if (round((float)($r['purchase_cost'] ?? 0), 2) < 0 || round((float)($r['sale_price'] ?? 0), 2) < 0) {
                throw new CommonException("第{$n}台：价格不能为负");
            }
        }

        $inbound  = new ErpStandaloneInboundService();
        $outbound = new ErpOutboundService();

        $ok = [];
        $fail = [];
        foreach ($rows as $i => $r) {
            $n = $i + 1;
            $model    = trim((string)($r['model'] ?? ''));
            $imei     = trim((string)($r['imei'] ?? ''));
            $sn       = trim((string)($r['sn'] ?? ''));
            $rowRemark = trim((string)($r['remark'] ?? '')) ?: ($batchRemark ?: '快速过账');
            $buyCost  = round((float)($r['purchase_cost'] ?? 0), 2);
            $salePrice = round((float)($r['sale_price'] ?? 0), 2);

            try {
                // 1) 入库 → 最小资产 + 应付供货人(挂账, 未付)
                $inRes = $inbound->create([
                    'business_type'          => 'purchase',
                    'counterparty_id'        => $supplierCpId,
                    'counterparty_member_id' => $supplierMemberId,
                    'model'                  => $model,
                    'imei'                   => $imei,
                    'sn'                     => $sn,
                    'purchase_cost'          => $buyCost,
                    'paid_amount'            => 0,           // 应付全挂,后续核销
                    'need_refurb'            => false,
                    'remark'                 => $rowRemark,
                    // 不传 warehouse/location:过账不关心库位,下面直接翻为可出库
                ]);

                $assetId = (int)($inRes['created_assets'][0]['id'] ?? 0);
                if ($assetId <= 0) {
                    // 兜底:按最近建档的本供货人资产取
                    $assetId = (int)(ErpAsset::where([['site_id', '=', $this->site_id]])
                        ->where('imei', $imei !== '' ? $imei : '___none___')
                        ->order('id', 'desc')->value('id') ?: 0);
                }
                if ($assetId <= 0) {
                    throw new CommonException('入库未返回资产');
                }

                // 2) 资产翻为「在库」可出库(过账跳过仓库确认/拍照)
                // 注意: 过账不走 confirmInbound, 须在此显式把 current_cost 落为进货成本,
                // 否则成本=0、毛利虚高(历史 bug 即源于此处漏设)。
                ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])
                    ->update([
                        'inventory_status' => ErpDict::INVENTORY_IN_STOCK,
                        'current_cost'     => $buyCost,
                        'stock_in_at'      => time(),
                        'update_at'        => time(),
                    ]);

                // 3) 出库(挂账, 买家) + 回填价 → 应收买家(挂账)
                $outRes = $outbound->createOutbound([
                    'outbound_type'   => ErpDict::OUTBOUND_TYPE_PEER_SALE,
                    'settle_mode'     => ErpDict::SETTLE_MODE_LATER,  // 挂账,不强制即时收款
                    'counterparty_id' => $buyerMemberId,             // 买家 = member_id
                    'items'           => [['asset_id' => $assetId, 'sale_price' => $salePrice]],
                    'remark'          => $rowRemark,
                ]);
                $outboundId = (int)($outRes['outbound_id'] ?? 0);
                if ($outboundId <= 0) {
                    throw new CommonException('出库未返回单号');
                }

                // 回填价 → 生成应收(挂账, collect_now=false)
                $items = ErpOutboundItem::where([['site_id', '=', $this->site_id], ['outbound_id', '=', $outboundId]])
                    ->column('id');
                $itemPrices = [];
                foreach ($items as $itemId) {
                    $itemPrices[] = ['item_id' => (int)$itemId, 'sale_price' => $salePrice];
                }
                if (!empty($itemPrices)) {
                    $outbound->fillPrice($outboundId, $itemPrices, ['collect_now' => false]);
                }

                $ok[] = [
                    'no'           => $n,
                    'model'        => $model,
                    'imei'         => $imei,
                    'asset_id'     => $assetId,
                    'outbound_id'  => $outboundId,
                    'purchase_cost' => $buyCost,
                    'sale_price'   => $salePrice,
                    'profit'       => round($salePrice - $buyCost, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('[erp] 快速过账失败 第' . $n . '台: ' . $e->getMessage());
                $fail[] = ['no' => $n, 'model' => $model, 'imei' => $imei, 'message' => $e->getMessage()];
            }
        }

        $sumBuy = array_sum(array_column($ok, 'purchase_cost'));
        $sumSale = array_sum(array_column($ok, 'sale_price'));
        return [
            'success_count' => count($ok),
            'fail_count'    => count($fail),
            'total_buy'     => round((float)$sumBuy, 2),
            'total_sale'    => round((float)$sumSale, 2),
            'total_profit'  => round((float)($sumSale - $sumBuy), 2),
            'ok'            => $ok,
            'fail'          => $fail,
        ];
    }
}
