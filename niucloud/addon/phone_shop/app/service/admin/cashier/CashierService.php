<?php
// +----------------------------------------------------------------------
// | 二手商城 · 代下单收银台(点菜式开单)
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\admin\cashier;

use addon\phone_shop\app\service\core\order\CoreOfflineSaleService;
use addon\phone_shop\app\service\admin\goods\GoodsService;
use addon\phone_shop\app\service\admin\MemberLevelNoService;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\Category;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 代下单收银台:店员替会员一次下单多台。
 * 复用 CoreOfflineSaleService::createSaleOrder(按 sku),用同一 outbound_no 把多台归并成一笔订单。
 * 首期:一机一价(unique)为主;标品数量、打包价均摊下一轮。
 */
class CashierService extends BaseAdminService
{
    /**
     * 收银台商品列表:富检索(关键词/IMEI/内存/成色/分类)+ 会员价(官方算法)+ 质检详情。
     * @param array $p keyword, imei, memory_group, condition_grade, category_id, member_id, page, limit, sort_by, sort_direction
     * @return array
     */
    /**
     * 收银台分类树:只返回"自身或子级有在售商品"的分类(空分类不显示),支持三级。
     */
    public function categoryTree(): array
    {
        // 1) 在售商品引用到的分类 id(goods_category 存所选分类 id 数组,可能是末级或整条路径)
        $rows = (new Goods())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['is_online_sellable', '=', 1],
        ])->column('goods_category');
        $referenced = [];
        foreach ($rows as $gc) {
            $arr = is_array($gc) ? $gc : (is_string($gc) ? (json_decode($gc, true) ?: []) : []);
            foreach ((array) $arr as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) $referenced[$cid] = true;
            }
        }
        if (empty($referenced)) return [];

        // 2) 全部可见分类(扁平)
        $cats = (new Category())->where([['site_id', '=', $this->site_id], ['is_show', '=', 1]])
            ->field('category_id,category_name,image,level,pid,sort')
            ->order('sort desc, category_id asc')->select()->toArray();
        if (empty($cats)) return [];
        $byId = [];
        foreach ($cats as $c) $byId[(int) $c['category_id']] = $c;

        // 3) 保留集 = 被引用的分类 + 其全部祖先(末级有货则其父级也保留)
        $keep = [];
        foreach (array_keys($referenced) as $cid) {
            $cur = (int) $cid;
            $guard = 0;
            while ($cur > 0 && isset($byId[$cur]) && $guard++ < 10) {
                $keep[$cur] = true;
                $cur = (int) $byId[$cur]['pid'];
            }
        }

        // 4) 组树(只含保留节点)
        $childMap = [];
        foreach ($cats as $c) {
            if (!isset($keep[(int) $c['category_id']])) continue;
            $childMap[(int) $c['pid']][] = $c;
        }
        $build = function ($pid) use (&$build, $childMap) {
            $list = $childMap[(int) $pid] ?? [];
            foreach ($list as &$node) {
                $node['children'] = $build((int) $node['category_id']);
            }
            unset($node);
            return $list;
        };
        return $build(0);
    }

    public function goods(array $p): array
    {
        $memberId = (int) ($p['member_id'] ?? 0);
        $goodsService = new GoodsService();
        $memberInfo = $memberId > 0 ? $goodsService->getMemberInfo($memberId) : [];

        $query = (new GoodsSku())->alias('sku')
            ->join((new Goods())->getTable() . ' goods', 'goods.goods_id = sku.goods_id')
            ->where([
                ['goods.site_id', '=', $this->site_id],
                ['goods.status', '=', 1],
                ['goods.is_online_sellable', '=', 1],
            ]);
        $kw = trim((string) ($p['keyword'] ?? ''));
        if ($kw !== '') {
            $query->where('goods.goods_name|goods.sub_title', 'like', '%' . $kw . '%');
        }
        $imei = trim((string) ($p['imei'] ?? ''));
        if ($imei !== '') {
            $query->where('sku.sku_no', 'like', '%' . $imei . '%');
        }
        $mem = $this->asList($p['memory_group'] ?? []);
        if (!empty($mem)) {
            [$memoryWhere, $memoryBind] = CashierGoodsOrder::memoryFilter($mem);
            $query->whereRaw($memoryWhere, $memoryBind);
        }
        $grade = $this->asList($p['condition_grade'] ?? []);
        if (!empty($grade)) {
            $query->whereIn('sku.condition_grade', $grade);
        }
        // 三级分类:优先按"选中节点 + 其全部子级"的 id 集合匹配(商品分类存的是所选路径/末级,
        // 用集合做 OR 命中,选一级也能带出其下二三级的商品)。无集合时回退单 id。
        $catIds = array_values(array_filter(array_map('intval', $this->asList($p['category_ids'] ?? []))));
        if (empty($catIds) && (int) ($p['category_id'] ?? 0) > 0) {
            $catIds = [(int) $p['category_id']];
        }
        // goods_category 存的是"字符串"数组(如 ["1662"]),用 LIKE '%"id"%' 匹配,
        // 不能用数字 JSON_CONTAINS(类型不符,永远查不到)。
        if (!empty($catIds)) {
            $query->where(function ($q) use ($catIds) {
                foreach ($catIds as $cid) {
                    $q->whereOr('goods.goods_category', 'like', '%"' . (int) $cid . '"%');
                }
            });
        }

        $field = 'sku.sku_id,sku.goods_id,sku.sku_no,sku.price,sku.stock,sku.condition_grade,sku.member_price,sku.device_snapshot,sku.erp_asset_id,'
            . 'goods.goods_name,goods.sub_title,goods.goods_cover,goods.goods_image,goods.memory_group,goods.member_discount';
        $field .= ',(' . CashierGoodsOrder::memoryExpression() . ') AS cashier_memory';
        $sortBy = (string) ($p['sort_by'] ?? '');
        if ($sortBy === 'price') {
            $levelKey = (int) ($memberInfo['memberLevelData']['level_no'] ?? 0);
            if ($levelKey <= 0 && !empty($memberInfo['member_level'])) {
                $levelKey = MemberLevelNoService::idToNo($this->site_id, (int) $memberInfo['member_level']);
            }
            $levelKey = $levelKey > 0 ? $levelKey : (int) ($memberInfo['member_level'] ?? 0);
            $field .= ',(' . CashierGoodsOrder::priceExpression($memberInfo, $levelKey) . ') AS cashier_price';
        }
        $page  = max(1, (int) ($p['page'] ?? 1));
        $limit = min(120, max(1, (int) ($p['limit'] ?? 18)));
        $total = $query->count();
        $rows  = $query->field($field)->orderRaw(CashierGoodsOrder::order($sortBy, (string) ($p['sort_direction'] ?? 'desc')))
            ->page($page, $limit)->select()->toArray();

        $list = [];
        foreach ($rows as $r) {
            $price = round((float) $r['price'], 2);
            $memberPrice = $memberId > 0
                ? round((float) $goodsService->getMemberPrice($memberInfo, $r['member_discount'], $r['member_price'], $price), 2)
                : $price;
            $hasMember = $memberPrice > 0 && $memberPrice < $price;
            // 质检详情(建品时存的结构化质检)
            $snap = is_array($r['device_snapshot'] ?? null) ? $r['device_snapshot']
                : (is_string($r['device_snapshot'] ?? null) ? (json_decode($r['device_snapshot'], true) ?: []) : []);
            $list[] = [
                'goods_id'        => (int) $r['goods_id'],
                'sku_id'          => (int) $r['sku_id'],
                'erp_asset_id'    => (int) ($r['erp_asset_id'] ?? 0),
                'goods_name'      => (string) $r['goods_name'],
                'sub_title'       => (string) $r['sub_title'],
                'goods_cover'     => (string) $r['goods_cover'],
                'goods_image'     => (string) ($r['goods_image'] ?? ''),
                'imei'            => (string) $r['sku_no'],
                'memory_group'    => (string) $r['memory_group'],
                'memory_label'    => $r['cashier_memory'] === null ? '' : ((float) $r['cashier_memory'] >= 1024
                    ? ((float) $r['cashier_memory'] / 1024) . 'TB' : (float) $r['cashier_memory'] . 'GB'),
                'condition_grade' => (string) $r['condition_grade'],
                'stock'           => (int) $r['stock'],
                'price'           => $price,
                'member_price'    => $hasMember ? $memberPrice : $price,
                'has_member_price' => $hasMember,
                'check_meta'      => $snap['check_meta'] ?? [],
            ];
        }
        return ['total' => $total, 'page' => $page, 'data' => $list];
    }

    /** 入参规整为去空字符串数组(兼容单值字符串 / 数组 / 逗号分隔) */
    private function asList($v): array
    {
        if (is_string($v)) {
            $v = $v === '' ? [] : explode(',', $v);
        }
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn($x) => trim((string) $x), $v), static fn($x) => $x !== ''));
    }

    /**
     * 代下单结算:直接对接 ERP 生成一张出货单(不建商城订单,ERP 为唯一事实源)。
     *   - 买家 = 会员(本系统"往来单位锚 = member_id",counterparty_id 即 member_id)
     *   - 现结(offline_cash → settle_mode=now):出库即收款入账,必须选收款户头(capital_account_id)
     *   - 挂账(offline_credit → settle_mode=later):生成 ERP 应收,事后再结
     *   - 批量:本次多台 = 同一出货单的多条明细;单台取消/整单退回走 ERP partialReturn/cancelOutbound
     *   - 商城侧:对应商品仅下架(build_mall_order=false),退回时由 ERP→商城单向同步回在售
     *
     * @param array $data member_id, payment_mode(offline_cash|offline_credit), capital_account_id, items[{sku_id|asset_id,sale_price}]
     * @return array
     */
    public function checkout(array $data): array
    {
        $memberId = (int) ($data['member_id'] ?? 0);
        if ($memberId <= 0) {
            throw new AdminException('请选择会员(买家)');
        }
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (empty($items)) {
            throw new AdminException('请先加入商品');
        }

        // 结算方式:现结=出库即收款入账 / 挂账=ERP 应收
        $isCredit   = ((string) ($data['payment_mode'] ?? 'offline_cash')) === 'offline_credit';
        $settleMode = $isCredit ? 'later' : 'now';
        // 现结开关门控:后台「收款设置」关闭现结后,收银台只能挂账(账目由财务中心收款)。
        if (!$isCredit
            && class_exists('\addon\hsx_erp\app\service\core\ErpConfigService')
            && !\addon\hsx_erp\app\service\core\ErpConfigService::instantSettleAllowed($this->site_id)) {
            throw new AdminException('现结已被关闭,请改用「挂账」开单,款项由财务中心收取');
        }
        $capitalAccountId = (int) ($data['capital_account_id'] ?? 0);
        // 多账户分笔现结:微信一笔、支付宝一笔…合计须等于成交额(由 ERP createOutbound 兜底校验)
        $payments = [];
        foreach (is_array($data['payments'] ?? null) ? $data['payments'] : [] as $pm) {
            $accId = (int) ($pm['account_id'] ?? 0);
            $amt   = round((float) ($pm['amount'] ?? 0), 2);
            if ($accId > 0 && $amt > 0) {
                $payments[] = ['account_id' => $accId, 'method' => (string) ($pm['method'] ?? ''), 'amount' => $amt];
            }
        }
        if (!$isCredit && $capitalAccountId <= 0 && empty($payments)) {
            throw new AdminException('现结需选择收款户头');
        }

        // 解析为 ERP 出库明细:每台 = {asset_id, sale_price}
        $outItems = [];
        $missing  = [];
        foreach ($items as $it) {
            $skuId = (int) ($it['sku_id'] ?? 0);
            if ($skuId <= 0 && (int) ($it['goods_id'] ?? 0) > 0) {
                $skuId = (int) (new GoodsSku())->where('goods_id', (int) $it['goods_id'])->order('sku_id asc')->value('sku_id');
            }
            $assetId = (int) ($it['asset_id'] ?? $it['erp_asset_id'] ?? 0);
            if ($assetId <= 0 && $skuId > 0) {
                $assetId = (int) (new GoodsSku())->where('sku_id', $skuId)->value('erp_asset_id');
            }
            if ($assetId <= 0) {
                $missing[] = $skuId ?: '?';
                continue;
            }
            $outItems[] = [
                'asset_id'   => $assetId,
                'sale_price' => round((float) ($it['sale_price'] ?? 0), 2),
            ];
        }
        if (!empty($missing)) {
            throw new AdminException('以下商品未关联 ERP 设备,无法出库(sku:' . implode(',', $missing) . ')');
        }
        if (empty($outItems)) {
            throw new AdminException('没有可出库的设备');
        }

        $erpSvc = '\\addon\\hsx_erp\\app\\service\\admin\\ErpOutboundService';
        if (!class_exists($erpSvc)) {
            throw new AdminException('ERP 模块未启用,无法生成出货单');
        }
        $res = (new $erpSvc())->createOutbound([
            'outbound_type'      => 'peer_sale',
            'sale_channel'       => 'mall',       // 商城销售口径
            'counterparty_id'    => $memberId,    // 往来单位锚 = 会员 member_id
            'settle_mode'        => $settleMode,
            'capital_account_id' => $capitalAccountId,
            'payments'           => $payments,    // 多账户分笔现结(空则走单户头)
            'items'              => $outItems,
            // 建一张"ERP 托管展示单"(order_from=offline + relate_source=出库单号):仅展示+打单+物流,
            // 不碰钱;关单/退款/取消/改价由护栏禁止,金额与收款一律以 ERP 为准。
            'build_mall_order'   => true,
        ]);

        return [
            'outbound_id' => (int) ($res['outbound_id'] ?? 0),
            'outbound_no' => (string) ($res['outbound_no'] ?? ''),
            'ok_count'    => count($outItems),
            'settle_mode' => $settleMode,
        ];
    }
}
