<?php

declare(strict_types=1);

namespace addon\phone_shop\app\listener\notice_template;

use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\GoodsSubscription;
use addon\phone_shop\app\model\goods\GoodsSubscriptionMatch;
use addon\phone_shop\app\service\core\goods\CoreGoodsNoticeService;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 筛选订阅命中通知数据。
 */
class GoodsMatch extends BaseNoticeTemplate
{
    public function handle(array $params)
    {
        if (($params['key'] ?? '') !== 'phone_shop_goods_match') return;

        $match = (new GoodsSubscriptionMatch())
            ->where('match_id', (int)($params['data']['match_id'] ?? 0))
            ->findOrEmpty()
            ->toArray();
        if (empty($match)) return;

        $goods = (new Goods())->where([
            [ 'site_id', '=', (int)$match['site_id'] ],
            [ 'goods_id', '=', (int)$match['goods_id'] ],
        ])->field('goods_id,site_id,goods_name,brand_id,create_time')->findOrEmpty()->toArray();
        $subscription_name = (string)(new GoodsSubscription())
            ->where('subscription_id', (int)$match['subscription_id'])
            ->value('subscription_name');
        $price = isset($match['price_snapshot'])
            ? (string)$match['price_snapshot']
            : (string)(new GoodsSku())->where('sku_id', (int)$match['sku_id'])->value('price');
        $changeType = (string)($match['change_type'] ?? 'new_listing');
        $changeTypeName = $changeType === 'price_changed' ? '订阅商品调价' : '订阅分类上新';
        $changeSummary = (string)($match['change_summary'] ?? '有符合订阅条件的新商品上架');
        $previousPrice = '';
        if ($changeType === 'price_changed' && array_key_exists('price_snapshot', $match)) {
            $previous = (new GoodsSubscriptionMatch())
                ->where([
                    [ 'subscription_id', '=', (int)$match['subscription_id'] ],
                    [ 'goods_id', '=', (int)$match['goods_id'] ],
                    [ 'match_id', '<', (int)$match['match_id'] ],
                    [ 'notify_status', '=', 1 ],
                ])
                ->order('match_id desc')
                ->field('price_snapshot')
                ->findOrEmpty()
                ->toArray();
            $previousPrice = (string)($previous['price_snapshot'] ?? '');
        }
        $page = 'addon/phone_shop/pages/goods/detail?goods_id=' . (int)$match['goods_id'];
        $url = get_wap_domain((int)$match['site_id']) . '/' . $page;
        $arrival = CoreGoodsNoticeService::arrivalVariables((int)$match['site_id'], $goods ? [$goods] : [], (int)$match['create_time']);
        // 调价不能算成又上架一台；保留原价格变量供公众号/短信使用。
        if ($changeType !== 'new_listing') $arrival['goods_count'] = '0';

        return $this->toReturn([
            '__wechat_page' => $url,
            '__weapp_page' => $page,
            'goods_name' => (string)($goods['goods_name'] ?? ''),
            'goods_price' => $price,
            'subscription_name' => $subscription_name,
            'change_type_name' => $changeTypeName,
            'change_summary' => $changeSummary,
            'notice_remark' => $changeType === 'price_changed'
                ? '调价提醒：' . $changeSummary
                : '上新：' . (string)($goods['goods_name'] ?? '订阅商品'),
            'old_price' => $previousPrice,
            'new_price' => $price,
            'match_time' => date('Y-m-d H:i:s', (int)$match['create_time']),
            'url' => $url,
        ] + $arrival, [
            'member_id' => (int)$match['member_id'],
        ]);
    }
}
