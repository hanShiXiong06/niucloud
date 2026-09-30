<?php
declare (strict_types=1);

namespace addon\phone_shop\app\listener\order;

use addon\phone_shop\app\service\core\goods\CoreGoodsStatService;
use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\order\OrderDiscounts;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\coupon\CoreCouponMemberService;
use addon\phone_shop\app\service\core\goods\CoreGoodsSaleNumService;
use addon\phone_shop\app\service\core\goods\CoreGoodsStockService;
use addon\phone_shop\app\service\core\order\CoreInvoiceService;
use addon\phone_shop\app\service\core\order\CoreOrderLogService;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;
use think\facade\Db;
use think\facade\Log;
use app\service\core\notice\NoticeService;

/**
 * 订单关闭后操作
 */
class AfterShopOrderClose
{

    public function handle($data)
    {
        Log::write('订单AfterShopOrderClose' . json_encode($data));
        try {
            $order_data = $data['order_data'];
            if (in_array((string)($order_data['payment_mode'] ?? ''), ['offline_pending', 'offline_cash', 'offline_credit'], true)) {
                OfflineOrderSchemaService::ensure();
                $reason = trim((string)($data['close_remark'] ?? $order_data['close_remark'] ?? ''));
                if ($reason === '' && (string)($order_data['payment_mode'] ?? '') === 'offline_pending') {
                    $reason = '锁单有效期内未完成付款确认，设备已解除锁定。';
                }
                OrderOfflineRecord::where([
                    ['site_id', '=', (int)$order_data['site_id']], ['order_id', '=', (int)$order_data['order_id']],
                ])->update([
                    'status' => 'closed',
                    'close_reason' => mb_substr($reason, 0, 500),
                    'update_time' => time(),
                ]);
                if ((string)($order_data['payment_mode'] ?? '') === 'offline_pending') {
                    NoticeService::send((int)$order_data['site_id'], 'phone_shop_offline_order_status', [
                        'order_id' => (int)$order_data['order_id'],
                        'status_name' => '订单已关闭',
                        'status_remark' => $reason,
                    ]);
                }
            }

            // 线下/代下单(order_from=offline):库存与销量由 CoreOfflineSaleService 及 ERP 反向同步统一管理,
            // 不走标准商城关单回滚,否则库存被双重恢复(returnSaleItemByAsset 置1 + 此处再 +num=2)、sale_num 减成负数。
            if ((string)($order_data['order_from'] ?? '') === 'offline') {
                return true;
            }

            //退还优惠项
            $order_discount_where = array(
                ['order_id', '=', $order_data['order_id']]
            );

            $order_discount = (new OrderDiscounts())->where($order_discount_where)->select();

            if (!$order_discount->isEmpty()) {
                $recover_list = [];
                foreach ($order_discount as $v) {
                    $item_discount_type = $v['discount_type'];
                    $recover_list[$item_discount_type][] = $v['discount_type_id'];

                }

                foreach ($recover_list as $item_discount_type => $discount_type_ids) {
                    switch ($item_discount_type) {
                        case 'coupon'://优惠券
                            (new CoreCouponMemberService())->recover($discount_type_ids);
                            break;
                    }
                }
            }
            $order_goods_where = array(
                ['order_id', '=', $order_data['order_id']],
                ['is_gift', '=', 0],
            );
            $order_goods_data = (new OrderGoods())->where($order_goods_where)->select()->toArray();

            //返还商品库存
            $core_goods_stock_service = new CoreGoodsStockService();
            $inc_data = [];
            foreach ($order_goods_data as $v) {
                // 新订单已在关单事务释放，退款设备由实物退回流程恢复，不能重复加库存。
                if (\addon\phone_shop\app\service\core\order\CoreOrderInventoryService::isManaged($v)) continue;
                $extend = \addon\phone_shop\app\service\core\order\ErpDeviceSnapshot::decode($v['extend'] ?? []);
                if (isset($extend['erp_return'])) continue;
                if (($data['close_type'] ?? '') === OrderDict::REFUND_CLOSE) {
                    $sku = \addon\phone_shop\app\model\goods\GoodsSku::where('site_id', (int)$order_data['site_id'])->where('sku_id', (int)$v['sku_id'])->findOrEmpty();
                    if (!$sku->isEmpty() && ((int)$sku->is_unique === 1 || (int)$sku->erp_asset_id > 0 || preg_match('/^\d{15}$/D', (string)$sku->sku_no))) continue;
                }
                $stock = $v['num'];
                $inc_data['goods'][] = [
                    'stock' => Db::raw("stock+" . $stock),
                    'goods_id' => $v['goods_id']
                ];
                $inc_data['sku'][] = [
                    'stock' => Db::raw(" stock+" . $stock),
                    'sku_id' => $v['sku_id']
                ];
            }
            $core_goods_stock_service->batchUpdateStock($inc_data);
            //商品累计销量
            //累减销量
            $dec_data = [];
            $core_goods_sale_num_service = new CoreGoodsSaleNumService();
            foreach ($order_goods_data as $v) {
                // 商品销量累减 - 下单数（不剔除退款订单）
                if (empty($order_data['pay_time'])) {
                    //商品累计销量
                    $stock = $v['num'];
                    $dec_data['goods'][] = [
                        'sale_num' => Db::raw("sale_num-" . $stock),
                        'goods_id' => $v['goods_id']
                    ];
                    $dec_data['sku'][] = [
                        'sale_num' => Db::raw(" sale_num-" . $stock),
                        'sku_id' => $v['sku_id']
                    ];
                    //TODO::可以优化  后置
                    CoreGoodsStatService::decStat(['site_id' => $v['site_id'], 'goods_id' => $v['goods_id'], 'time' => $order_data['create_time'], 'sale_num' => $v['num']]);
                }
            }
            $core_goods_sale_num_service->batchUpdateSaleNum($dec_data);


            //发票改变状态........
            (new CoreInvoiceService())->close($order_data['invoice_id']);
            //发布日志
            $main_type = $data['main_type'];
            $main_id = $data['main_id'] ?? 0;
            (new CoreOrderLogService())->add([
                'order_id' => $order_data['order_id'],
                'status' => OrderDict::CLOSE,
                'main_type' => $main_type,//todo  可以是传入的
                'main_id' => $main_id,
                'type' => OrderDict::ORDER_CLOSE_ACTION,
                'content' => ''
            ]);
            //todo 消息发送

            //新人专享活动退还参与资格
            if ($order_data['activity_type'] == ActiveDict::NEWCOMER_DISCOUNT) {
                event("NewcomerActiveJoin", ['site_id' => $order_data['site_id'], 'member_id' => $order_data['member_id'], 'is_join' => 0, 'order_id' => $order_data['order_id']]);
            }
        } catch (\Exception $e) {
            Log::write('订单AfterShopOrderClose失败' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
    }
}
