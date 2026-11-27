<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\admin\cashier;

use addon\phone_shop\app\service\core\order\CoreOfflineSaleService;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use core\exception\AdminException;
use think\facade\Db;

/** 商城持有选机/交付原单；ERP 持有资产和账款。这里不调用外部 ERP 或支付渠道。 */
final class CashierDeviceCheckout
{
    public function run(int $site, int $staff, string $staffName, array $data): array
    {
        $member = (int)($data['member_id'] ?? 0);
        $mode = (string)($data['payment_mode'] ?? '');
        $kind = (string)($data['sale_kind'] ?? 'retail');
        if ($site <= 0 || $staff <= 0 || $member <= 0) throw new AdminException('请用本站员工账号登录并选择买家');
        if (!in_array($mode, ['offline_cash','offline_credit'], true) || !in_array($kind, ['retail','peer'], true)) throw new AdminException('请选择有效的销售与结算方式');
        $key = (string)($data['request_id'] ?? '');
        if ($key === '') $key = bin2hex(random_bytes(16));
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/D', $key)) throw new AdminException('开单标识无效，请刷新收银台');
        $orderNo = 'CS'.strtoupper(substr(hash('sha256', $site.':'.$key), 0, 28));
        $lines = [];
        foreach ((array)($data['items'] ?? []) as $line) {
            $id = (int)($line['sku_id'] ?? 0);
            if ($id <= 0 || isset($lines[$id])) throw new AdminException('请选择有效的商城设备，同一台不能重复选择');
            $lines[$id] = ['asset' => (int)($line['asset_id'] ?? 0), 'price' => self::cents($line['sale_price'] ?? null)];
        }
        if (!$lines) throw new AdminException('请先选择本次交付的设备');
        ksort($lines, SORT_NUMERIC);
        $total = array_sum(array_column($lines, 'price'));
        $accounts = [];
        $single = (int)($data['capital_account_id'] ?? 0);
        foreach ((array)($data['payments'] ?? []) as $p) {
            $account = (int)($p['account_id'] ?? 0);
            if ($account <= 0) throw new AdminException('请为每笔选择收款账户');
            $accounts[$account] = ($accounts[$account] ?? 0) + self::cents($p['amount'] ?? null);
        }
        if ($accounts && $single > 0) throw new AdminException('单笔和分笔收款不能同时填写');
        if ($single > 0) $accounts[$single] = $total;
        if ($mode === 'offline_cash' && (!$accounts || array_sum($accounts) !== $total)) throw new AdminException('请选择实际收款账户，分笔合计须等于成交总额');
        if ($mode === 'offline_credit' && $accounts) throw new AdminException('挂账不登记收款，请清除收款账户');
        ksort($accounts, SORT_NUMERIC);
        $signature = hash('sha256', json_encode([$member,$staff,$mode,$kind,$lines,$accounts], JSON_THROW_ON_ERROR));
        return Db::transaction(function () use ($site,$staff,$staffName,$member,$mode,$kind,$orderNo,$lines,$total,$accounts,$signature) {
            $rows = Db::name('phone_shop_goods_sku')->where('site_id',$site)->whereIn('sku_id',array_keys($lines))
                ->order('sku_id')->lock(true)->select()->toArray();
            // 同商品下不同串号也要串行计算商品总库存，在首次非锁定读建立快照前先锁定父商品。
            $goods = $rows ? Db::name('phone_shop_goods')->where('site_id',$site)->whereIn('goods_id',array_unique(array_column($rows,'goods_id')))
                ->order('goods_id')->lock(true)->select()->column(null,'goods_id') : [];
            // 部分站点的原订单表没有单号索引。不要用单号全表加锁，复用请求时只锁原单主键。
            $oldId = Db::name('phone_shop_order')->where(['site_id'=>$site,'order_no'=>$orderNo])->value('order_id');
            $old = $oldId ? Db::name('phone_shop_order')->where(['site_id'=>$site,'order_id'=>$oldId])->lock(true)->find() : null;
            if ($old) {
                $raw = Db::name('phone_shop_order_goods')->where(['site_id'=>$site,'order_id'=>$old['order_id']])->value('extend');
                $ext = ErpDeviceSnapshot::decode($raw);
                if (($old['order_from'] ?? '') !== 'offline' || !hash_equals((string)($ext['cashier_signature'] ?? ''),$signature)
                    || empty($old['relate_source']) || $old['relate_source'] === $orderNo) throw new AdminException('开单标识与已保存的单据不一致，请查询原单');
                return $this->result((int)$old['order_id'],$orderNo,(string)$old['relate_source'],count($lines),$mode)
                    + ['outbound_id'=>(int)($ext['cashier_sale_id'] ?? 0)];
            }
            if (count($rows) !== count($lines)) throw new AdminException('部分设备不属于本站或已删除，请刷新后重选');
            foreach ($rows as $sku) {
                $g = $goods[$sku['goods_id']] ?? [];
                if (!$g || (int)$g['status'] !== 1 || (int)$g['delete_time'] !== 0 || $g['sale_status'] !== 'available'
                    || (int)$g['is_online_sellable'] !== 1 || (int)$sku['stock'] <= 0) throw new AdminException('所选设备已售、已被预订或未上架，请刷新后重选');
                if ((int)$sku['erp_asset_id'] <= 0 || ($lines[$sku['sku_id']]['asset'] && $lines[$sku['sku_id']]['asset'] !== (int)$sku['erp_asset_id'])) throw new AdminException('商城设备与 ERP 资产未关联或已变更，请核对设备档案');
            }
            $now = time();
            $party = $this->erp('ErpPartyResolveRequested', [
                'event_name'=>'erp.party.resolve_requested.v1','event_version'=>1,'event_id'=>$orderNo.':buyer',
                'site_id'=>$site,'source_plugin'=>'phone_shop','member_id'=>$member,'role'=>'sale_customer','occurred_at'=>$now,
            ]);
            if (empty($party['party_id']) || (int)($party['member_id'] ?? 0) !== $member) throw new AdminException('未找到买家对应的本站 ERP 往来单位');
            $sale = new CoreOfflineSaleService();
            $orderId = 0;
            foreach ($rows as $sku) {
                $result = $sale->createSaleOrder([
                    'site_id'=>$site,'member_id'=>$member,'sku_id'=>$sku['sku_id'],'sale_price'=>$lines[$sku['sku_id']]['price']/100,
                    'buyer_type'=>$kind === 'peer' ? 'b' : 'c','payment_mode'=>$mode,'staff_id'=>$staff,
                    'outbound_no'=>$orderNo,'cashier_order_no'=>$orderNo,'cashier_signature'=>$signature,'result_status'=>'sold',
                    'cashier_parent_id'=>$orderId,'taker_name'=>(string)(($party['member_name'] ?? '') ?: $party['party_name']),
                ]);
                if (empty($result['ok']) || ($orderId && $orderId !== (int)$result['order_id'])) throw new AdminException('设备未能加入本次交付单，未出库：'.($result['message'] ?? '设备已有成交记录'));
                $orderId = (int)$result['order_id'];
            }
            $itemIds = Db::name('phone_shop_order_goods')->where(['site_id'=>$site,'order_id'=>$orderId])->column('order_goods_id','sku_id');
            $pay = ['mode'=>$mode,'status'=>$mode==='offline_cash' ? 'paid' : 'unpaid','pricing_identity'=>$kind,
                'gross_amount'=>$total/100,'merchant_net_amount'=>$total/100,'capital_account_id'=>(int)(array_key_first($accounts) ?? 0)];
            if (count($accounts)>1) foreach ($accounts as $id=>$amount) $pay['receipt_accounts'][] = ['account_id'=>$id,'amount'=>$amount/100];
            $facts = [];
            foreach ($rows as $sku) $facts[] = ['source_line_id'=>(string)$itemIds[$sku['sku_id']],'asset_id'=>(int)$sku['erp_asset_id'],'sale_price'=>$lines[$sku['sku_id']]['price']/100];
            $result = $this->erp('ErpSaleCreatedRequested', [
                'event_name'=>'erp.sale.created_requested','event_version'=>1,'event_id'=>$orderNo.':sale',
                'site_id'=>$site,'targets'=>['self_erp'],'source'=>['plugin'=>'phone_shop','type'=>'phone_shop.cashier_sale','name'=>'商城收银台','id'=>(string)$orderId,'order_no'=>$orderNo],
                'channel'=>['code'=>$kind==='peer'?'erp_peer':'erp_store'],'counterparty'=>['party_id'=>$party['party_id'],'name'=>$party['party_name']],
                'operator'=>['id'=>$staff,'name'=>$staffName],'occurred_at'=>$now,'items'=>$facts,'payment'=>$pay,
            ]);
            if (empty($result['sale_order_id']) || empty($result['sale_no'])) throw new AdminException('ERP 未返回销售单号，未确认交付');
            Db::name('phone_shop_order')->where(['site_id'=>$site,'order_id'=>$orderId])->update(['relate_source'=>$result['sale_no']]);
            $savedLines = Db::name('phone_shop_order_goods')->where(['site_id'=>$site,'order_id'=>$orderId])->column('extend','order_goods_id');
            foreach ($savedLines as $lineId=>$raw) {
                $ext=ErpDeviceSnapshot::decode($raw);
                $ext['cashier_sale_id']=(int)$result['sale_order_id'];
                Db::name('phone_shop_order_goods')->where(['site_id'=>$site,'order_goods_id'=>$lineId])
                    ->update(['extend'=>json_encode(ErpDeviceSnapshot::checkLength($ext),JSON_THROW_ON_ERROR)]);
            }
            return $this->result($orderId,$orderNo,(string)$result['sale_no'],count($lines),$mode) + ['outbound_id'=>(int)$result['sale_order_id']];
        });
    }

    private function erp(string $hook, array $fact): array
    {
        foreach ((array)event($hook,$fact) as $reply) {
            if (!is_array($reply) || ($reply['consumer'] ?? '') !== 'hsx_erp') continue;
            if (in_array($reply['status'] ?? '', ['processed','duplicate'],true)) return $reply;
            throw new AdminException((string)($reply['message'] ?? '本站 ERP 未接受本次开单'));
        }
        throw new AdminException('本站 ERP 未启用或销售接口未安装，未出库、未记账');
    }

    private function result(int $id,string $no,string $erpNo,int $count,string $mode): array
    {
        return ['order_id'=>$id,'order_no'=>$no,'outbound_no'=>$erpNo,'ok_count'=>$count,'settle_mode'=>$mode==='offline_credit'?'later':'now'];
    }

    private static function cents($value): int
    {
        if ((!is_scalar($value)) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',(string)$value) || (float)$value <= 0) throw new AdminException('成交与收款金额必须大于零，最多两位小数');
        return (int)round((float)$value*100);
    }
}
