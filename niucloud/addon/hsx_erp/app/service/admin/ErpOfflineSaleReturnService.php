<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleReturnItem;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 线下退货只消费确定的原销售关系，不用 IMEI 模糊匹配，不发起实际转账。 */
class ErpOfflineSaleReturnService extends BaseAdminService
{
    public function handle(array $data): array
    {
        if ((int)($data['site_id'] ?? 0) !== (int)$this->site_id || (int)$this->site_id <= 0
            || ($data['source_plugin'] ?? '') !== 'phone_shop' || (int)($data['order_id'] ?? 0) <= 0) {
            throw new CommonException('退货请求站点或来源不正确');
        }
        $apply = ($data['action'] ?? '') === 'confirm';
        if (!in_array($data['action'] ?? '', ['preview', 'confirm'], true)) throw new CommonException('退货操作不正确');
        if ($apply && !Db::connect()->getPdo()->inTransaction()) throw new CommonException('退货必须与商城原单使用同一事务');
        $sales = ErpSaleOrder::where('site_id', $this->site_id)->where(function ($q) use ($data) {
            $q->where(function ($sub) use ($data) {
                $sub->where('origin_plugin', 'phone_shop')->where('origin_id', (string)$data['order_id']);
            });
            if (!empty($data['relate_source'])) $q->whereOr('sale_no', (string)$data['relate_source']);
        })->order('id asc')->lock($apply)->select();
        if ($sales->isEmpty()) throw new CommonException('ERP 未找到原销售账目，请先核对订单同步；本次未关单、未恢复库存、未生成退款');
        $saleMap = [];
        $paidMap = [];
        $returnService = new ErpSaleReturnService();
        foreach ($sales as $sale) {
            if (in_array((string)$sale->payment_mode, ['online', 'wechat_online'], true)) {
                throw new CommonException('该订单有线上支付记录，请在商城走原路退款，不能登记线下退款');
            }
            $saleMap[(int)$sale->id] = $sale;
            $paidMap[(int)$sale->id] = $returnService->receivedBySaleItem((int)$sale->id);
        }
        $items = ErpSaleItem::where('site_id', $this->site_id)->whereIn('sale_order_id', array_keys($saleMap))->lock($apply)->select();
        $plan = [];
        foreach ((array)($data['lines'] ?? []) as $line) {
            $matches = [];
            foreach ($items as $item) {
                if ((string)$item->external_line_id === (string)$line['order_goods_id']
                    || ((string)$item->external_line_id === '' && (int)($line['asset_id'] ?? 0) > 0 && (int)$item->asset_id === (int)$line['asset_id'])) {
                    $matches[] = $item;
                }
            }
            $row = ['order_goods_id' => (int)$line['order_goods_id'], 'can_return' => false, 'reason' => '原销售明细尚未唯一关联，请检查商城与 ERP 同步'];
            if (count($matches) !== 1) { $plan[] = $row; continue; }
            $item = $matches[0]; $sale = $saleMap[(int)$item->sale_order_id];
            $price = round((float)$item->sale_price, 2);
            $paid = min($price, (float)($paidMap[(int)$sale->id][(int)$item->id] ?? 0));
            $row += ['sale_order_id' => (int)$sale->id, 'sale_item_id' => (int)$item->id, 'asset_id' => (int)$item->asset_id,
                'return_amount' => $price, 'refund_amount' => $paid, 'offset_amount' => round($price - $paid, 2),
                'return_id' => 0, 'return_no' => '', 'returned' => false, 'requires_location' => false];
            $prior = ErpSaleReturnItem::alias('i')->join((new ErpSaleReturnOrder())->getTable() . ' r', 'r.id=i.return_id AND r.site_id=i.site_id')
                ->where('i.site_id', $this->site_id)->where('i.sale_item_id', (int)$item->id)->where('r.status', '<>', 'cancelled')
                ->field('r.id,r.return_no,r.status,r.business_type')->order('r.id desc')->select()->toArray();
            // 合法撤销后再次退回是新一轮业务；同一轮重复点击仍使用同一个幂等键。
            $row['return_revision'] = ErpSaleReturnItem::alias('i')->join((new ErpSaleReturnOrder())->getTable() . ' r', 'r.id=i.return_id AND r.site_id=i.site_id')
                ->where('i.site_id', $this->site_id)->where('i.sale_item_id', (int)$item->id)->where('r.status', 'cancelled')->count();
            if ((string)$item->status === 'returned' && count($prior) === 1 && $prior[0]['status'] === 'confirmed'
                && $prior[0]['business_type'] !== 'after_sale_compensation') {
                $row['returned'] = true; $row['return_id'] = (int)$prior[0]['id']; $row['return_no'] = $prior[0]['return_no'];
                $refunds = ErpPayable::where('site_id', $this->site_id)->where('source_type', 'sale_return')
                    ->where('source_id', $row['return_id'])->where('status', '<>', 'void')->select()->toArray();
                $row['refund_pending'] = round(array_sum(array_map(static fn($p) => max(0, (float)$p['amount'] - (float)$p['settled_amount']), $refunds)), 2);
                $row['reason'] = $row['refund_pending'] > 0
                    ? '已退回 · 待财务退款 ¥' . number_format($row['refund_pending'], 2, '.', '')
                    : ($refunds ? '已退回 · 退款已登记' : '已退回 · 未收应收已冲销');
            } elseif ((string)$item->status !== 'sold' || $prior !== [] || (float)$item->refunded_amount > 0.001) {
                $row['reason'] = '已有退货、退款或补差记录，请先在 ERP 核对原处理结果';
            } elseif ((int)$line['num'] !== 1 || (float)$item->quantity !== 1.0) {
                $row['reason'] = '此入口按单台设备退回，多数量商品请先核对原销售明细';
            } elseif ($price < 0 || (int)$sale->party_id <= 0 && (int)($data['member_id'] ?? 0) <= 0) {
                $row['reason'] = '原客户身份不完整，请先核对原单客户';
            } else {
                $row['can_return'] = true; $row['reason'] = '';
                if ((int)$item->asset_id > 0) {
                    $asset = ErpAsset::where('site_id', $this->site_id)->where('id', (int)$item->asset_id)->lock($apply)->findOrEmpty();
                    if ($asset->isEmpty() || (string)$asset->status !== 'sold' || (int)$asset->sale_item_id !== (int)$item->id) {
                        $row['can_return'] = false; $row['reason'] = '设备的 ERP 销售状态已变化，请核对原单';
                    } else $row['requires_location'] = (int)$asset->warehouse_id <= 0 || (int)$asset->location_id <= 0;
                }
            }
            $plan[] = $row;
        }
        $token = hash('sha256', json_encode($plan));
        if (!$apply) return ['consumer' => 'hsx_erp', 'items' => $plan, 'preview_token' => $token];
        if (!hash_equals($token, (string)($data['preview_token'] ?? ''))) {
            throw new CommonException('订单收款或退回状态已变化，请重新打开退回窗口核对金额；未执行本次退回');
        }
        $selected = array_map('intval', (array)($data['order_goods_ids'] ?? []));
        if (!$selected || count(array_unique($selected)) !== count($selected)) throw new CommonException('请选择退回设备，不能重复选择');
        $selectedRows = array_values(array_filter($plan, static fn($row) => in_array($row['order_goods_id'], $selected, true)));
        if (count($selectedRows) !== count($selected)) throw new CommonException('退回明细不属于当前订单');
        $result = [];
        foreach ($selectedRows as $row) {
            if (!$row['can_return']) throw new CommonException($row['reason']);
            $sale = $saleMap[$row['sale_order_id']];
            if ((int)$sale->party_id <= 0) {
                $party = ErpPartyBridgeService::forSite((int)$this->site_id)->consume([
                    'site_id' => (int)$this->site_id, 'source_plugin' => 'phone_shop',
                    'event_id' => 'phone-return-party-' . $data['order_id'], 'member_id' => (int)$data['member_id'],
                    'role' => 'sale_customer', 'remark' => '商城原订单退回时确认客户身份',
                ]);
                $sale->save(['party_id' => (int)$party['party_id'], 'party_name' => $party['party_name']]);
                ErpReceivable::where('site_id', $this->site_id)->where('source_id', (int)$sale->id)->where('party_id', 0)
                    ->where(function ($q) { $q->where('source_type', 'sale')->whereOr('source_type', 'like', 'phone_shop.%'); })
                    ->update(['party_id' => (int)$party['party_id'], 'party_name' => $party['party_name']]);
            }
            $requestId = 'phone-return-' . $data['order_id'] . '-' . $row['order_goods_id'] . '-r' . $row['return_revision'];
            if ($row['asset_id'] > 0) {
                $ids = $returnService->createAndConfirm([
                    'sale_order_id' => $row['sale_order_id'], 'request_id' => $requestId, 'refund_mode' => 'payable',
                    'return_to_warehouse_id' => (int)($data['warehouse_id'] ?? 0), 'return_to_location_id' => (int)($data['location_id'] ?? 0),
                    'remark' => $data['reason'], 'items' => [['asset_id' => $row['asset_id'], 'return_price' => $row['return_amount']]],
                ]);
                $returnId = (int)$ids[0];
            } else $returnId = $returnService->returnNativeItem($row['sale_item_id'], $requestId, $data['reason']);
            $returnNo = (string)ErpSaleReturnOrder::where('site_id', $this->site_id)->where('id', $returnId)->value('return_no');
            $row['return_id'] = $returnId; $row['return_no'] = $returnNo;
            $row['refund_amount'] = (float)ErpPayable::where('site_id', $this->site_id)->where('source_type', 'sale_return')->where('source_id', $returnId)->sum('amount');
            $row['offset_amount'] = round($row['return_amount'] - $row['refund_amount'], 2);
            $result[] = $row;
        }
        return ['consumer' => 'hsx_erp', 'items' => $result];
    }
}
