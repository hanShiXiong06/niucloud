<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\dict\active\DiscountDict;
use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\model\discount\Discount;
use addon\phone_shop\app\model\discount\DiscountGoods;
use app\model\member\Member;
use core\base\BaseCoreService;

/**
 * 商品活动价服务层
 */
class CoreGoodsActivePriceService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 查询商品的活动价格
     * @param $sku_info
     * @param $site_id
     * @param $member_id
     * @return array
     */
    public function getActivePrice($sku_info, $site_id, $member_id)
    {
        return $this->resolvePrice($sku_info, (int)$site_id, (int)$member_id);
    }

    /**
     * 查询商品的展示价格
     * @param $sku_info
     * @param $site_id
     * @param $member_id
     * @return array
     */
    public function getShowPrice($sku_info, $site_id, $member_id)
    {
        return $this->resolvePrice($sku_info, (int)$site_id, (int)$member_id);
    }

    /**
     * 页面展示、订单确认和最终成交必须共用同一套价格解析。
     *
     * 返回值除最终价格外，还保留各候选价快照，订单营销计算不得再次
     * 对已经优惠过的价格重复计算会员折扣。
     */
    private function resolvePrice(array $sku_info, int $site_id, int $member_id): array
    {
        $original_price = round((float)($sku_info['price'] ?? 0), 2);
        $price_data = [
            [
                'show_price' => $original_price,
                'show_type' => GoodsDict::ORIGINAL_PRICE,
            ],
        ];
        $result_meta = [
            'original_price' => $original_price,
            'member_price' => $original_price,
            'discount_price' => null,
            'discount_id' => 0,
        ];

        if ($member_id > 0) {
            $member_info = (new Member())->where([
                ['site_id', '=', $site_id],
                ['member_id', '=', $member_id],
            ])->field('site_id,member_level')->with([
                'memberLevelData' => function ($query) {
                    $query->field('level_id,site_id,level_no,level_name,status,level_benefits');
                },
            ])->findOrEmpty()->toArray();
            $member_price = round((float)CoreMemberPriceService::calculate(
                $member_info,
                (string)($sku_info['member_discount'] ?? ''),
                $sku_info['member_price'] ?? '',
                $original_price
            ), 2);
            // 价格为 0 通常来自缺失/脏配置，不能静默生成 0 元订单。
            if ($member_price > 0) {
                $result_meta['member_price'] = $member_price;
                $price_data[] = [
                    'show_price' => $member_price,
                    'show_type' => GoodsDict::MEMBER_PRICE,
                ];
            }
        }

        $discount_goods = (new DiscountGoods())->where([
            ['goods_id', '=', (int)($sku_info['goods_id'] ?? 0)],
            ['sku_id', '=', (int)($sku_info['sku_id'] ?? 0)],
            ['site_id', '=', $site_id],
            ['status', '=', DiscountDict::ACTIVE],
            ['is_enabled', '=', DiscountDict::YES],
        ])->findOrEmpty();

        if (!$discount_goods->isEmpty()
            && $this->isDiscountActive((int)$discount_goods['discount_id'], $site_id)) {
            // 优先使用活动明细自己的价格；兼容旧数据再读取 SKU sale_price。
            $discount_price = round((float)($discount_goods['discount_price'] ?? 0), 2);
            if ($discount_price <= 0) {
                $discount_price = round((float)($sku_info['sale_price'] ?? 0), 2);
            }
            if ($discount_price > 0) {
                $result_meta['discount_price'] = $discount_price;
                $result_meta['discount_id'] = (int)$discount_goods['discount_id'];
                $price_data[] = [
                    'show_price' => $discount_price,
                    'show_type' => GoodsDict::DISCOUNT_PRICE,
                    'discount_id' => (int)$discount_goods['discount_id'],
                ];
            }
        }

        return array_merge($result_meta, $this->getMinShowPrice($price_data, $original_price));
    }

    /** 活动商品行处于 active 还不够，主活动也必须处于有效时间内。 */
    private function isDiscountActive(int $discount_id, int $site_id): bool
    {
        if ($discount_id <= 0) {
            return false;
        }
        $discount = (new Discount())->where([
            ['discount_id', '=', $discount_id],
            ['site_id', '=', $site_id],
            ['status', '=', DiscountDict::ACTIVE],
        ])->findOrEmpty();
        if ($discount->isEmpty()) {
            return false;
        }

        $now = time();
        $start_time = (int)$discount->getData('start_time');
        $end_time = (int)$discount->getData('end_time');
        return ($start_time <= 0 || $start_time <= $now)
            && ($end_time <= 0 || $end_time >= $now);
    }

    /**
     * 提取最低价格（优先 original_price）
     * @param $price_data
     * @param $original_price
     * @return array
     */
    private function getMinShowPrice(array $price_data, float $original_price): array
    {
        $priority = [
            'discount_price'  => 1,
            'member_price'    => 2,
            'original_price'  => 3,
        ];

        usort($price_data, function ($one, $two) use ($priority){
            $price_cmp = floatval($one[ 'show_price' ]) <=> floatval($two[ 'show_price' ]);

            if ($price_cmp === 0) {
                // 价格相等时按优先级排序，数字越小优先级越高
                return ($priority[ $one[ 'show_type' ] ] ?? 99) <=> ($priority[ $two[ 'show_type' ] ] ?? 99);
            }

            return $price_cmp;
        });

        $min = $price_data[0];

        if ($min[ 'show_type' ] !== GoodsDict::ORIGINAL_PRICE && floatval($min[ 'show_price' ]) == $original_price) {
            $min[ 'show_type' ] = GoodsDict::ORIGINAL_PRICE;
        }
        return $min;
    }



}
