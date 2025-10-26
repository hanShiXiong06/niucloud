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

namespace addon\home_service\app\service\api\goods;

use addon\home_service\app\dict\goods\GoodsDict;
use addon\home_service\app\model\goods\Browse;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\goods\GoodsSku;
use addon\home_service\app\service\core\strategy\CoreCityStrategyService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 *  商品足迹服务层
 */
class GoodsBrowseService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Browse();
    }

    /**
     * 查询足迹
     * @param $data
     * @return array
     * @throws \think\db\exception\DbException
     */
    public function getMemberGoodsBrowse($data)
    {
        $field = 'member_id,browse_time,goods_id,sku_id';
        $start_time = isset($data[ 'date' ][ 0 ]) ? strtotime($data[ 'date' ][ 0 ]) : 0;
        $end_time = isset($data[ 'date' ][ 1 ]) ? strtotime($data[ 'date' ][ 1 ]) : time();

        $search_model = $this->model->field($field)->where([ [ 'browse.site_id', '=', $this->site_id ], [ 'member_id', '=', $this->member_id ], [ 'goods.delete_time', '=', 0 ] ])
            ->whereBetweenTime('browse_time', $start_time, $end_time)
            ->withJoin([ 'goods' => [ 'site_id', 'goods_id', 'goods_name', 'goods_cover', 'sale_num', 'status', 'member_discount', 'buy_type' ] ])
            ->with([ 'goodsSku' ])
            ->order('browse_time desc');
        $list = $this->pageQuery($search_model);
        if (!empty($list[ 'data' ])) {
            $memberInfo = $this->getMemberInfo();
            foreach ($list[ 'data' ] as &$v) {
                if ($v['goods']['buy_type'] == GoodsDict::BUY) {
                    // 查询会员价
                    $member_price = $this->getMemberPrice($memberInfo, $v['goods']['member_discount'], $v['member_price'], $v['price']);
                }else{
                    $cityStrategyService = new CoreCityStrategyService();
                    $member_price = $cityStrategyService->getStrategyPrice($v['price'], $data['city_id'], $this->site_id);
                }
                $v['price'] = $member_price;
                $v['member_price'] = $member_price;
                $v[ 'browse_time_str' ] = strtotime($v[ 'browse_time' ]);
            }
        }

        return $list;
    }

    /**
     * 添加足迹
     */
    public function addGoodsBrowse($data)
    {
        $goods_info = ( new Goods() )->where([ [ 'site_id', '=', $this->site_id ], [ 'goods_id', '=', $data[ 'goods_id' ] ], [ 'delete_time', '=', 0 ] ])->field('site_id, goods_id')->findOrEmpty()->toArray();
        if (empty($goods_info)) throw new CommonException('HOME_SERVICE_GOODS_NOT_EXIST');//商品不存在
        $sku_id = ( new GoodsSku() )->where([ [ 'site_id', '=', $this->site_id ], [ 'goods_id', '=', $data[ 'goods_id' ] ], [ 'is_default', '=', 1 ] ])->value('sku_id');
        $data = array_merge($data, $goods_info);
        $data[ 'site_id' ] = $this->site_id;
        $data[ 'member_id' ] = $this->member_id;
        $data[ 'browse_time' ] = time();
        $data[ 'sku_id' ] = $sku_id;
        $info = $this->model->where([
            [ 'site_id', '=', $data[ 'site_id' ] ],
            [ 'member_id', '=', $data[ 'member_id' ] ],
            [ 'goods_id', '=', $data[ 'goods_id' ] ],
        ])->findOrEmpty();
        if ($info->isEmpty()) {
            $this->model->create($data);
        } else {
            $info->save([
                'browse_time' => $data[ 'browse_time' ]
            ]);
        }
        return true;
    }

    /**
     * 删除足迹
     */
    public function deleteGoodsBrowse($data)
    {
        $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'goods_id', 'in', $data[ 'goods_ids' ] ], [ 'member_id', '=', $this->member_id ] ])->delete();
        return true;
    }

    /**
     * 获取会员信息
     *
     * @return array
     */
    public function getMemberInfo()
    {
        $member_model = new Member();
        $member_field = 'member_level';
        $member_info = $member_model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->field($member_field)
            ->with([
                // 会员等级
                'memberLevelData' => function ($query) {
                    $query->field('level_id, site_id, level_name, status, level_benefits, level_gifts');
                },
            ])
            ->findOrEmpty()->toArray();
        return $member_info;
    }

    /**
     * 查询商品的会员价
     * @param $member_info
     * @param string $member_discount 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
     * @param string $member_price 会员价，json格式，指定会员价，数据结构为：{"level_12":"92.00","level_13":"72.00","level_14":"66.00","level_15":"45.00"}
     * @param $price
     * @return int|string
     */

    public function getMemberPrice($member_info, $member_discount, $member_price, $price)
    {
        // 获取城市ID并确保为整数
        $city_id = (int)request()->get('city_id', 0);
        $cityStrategyService = new CoreCityStrategyService();
        // 处理无需会员折扣的情况
        if (empty($member_discount) || empty($member_info) ||
            (!empty($member_info) && empty($member_info['member_level']))) {
            return $cityStrategyService->getStrategyPrice($price, $city_id, $this->site_id);
        }
        // 根据员折扣类型计算价格
        if ($member_discount === 'discount') {
            // 按照会员等级折扣计算
            $levelBenefits = $member_info['memberLevelData']['level_benefits'] ?? [];
            if (!empty($levelBenefits['discount']['is_use'])) {
                $discount = $levelBenefits['discount']['discount'] ?? 10; // 默认不打折
                $price = $price * $discount / 10;
            }
        } elseif ($member_discount === 'fixed_price') {
            // 指定会员价
            if (!empty($member_price)) {
                $memberPriceData = is_array($member_price) ? $member_price : json_decode($member_price, true);
                // 验证解析结果是否为数组
                if (is_array($memberPriceData)) {
                    $levelKey = 'level_' . $member_info['member_level'];
                    if (!empty($memberPriceData[$levelKey])) {
                        $price = $memberPriceData[$levelKey];
                    }
                }
            }
        }
        // 应用城市策略并格式化价格
        $finalPrice = $cityStrategyService->getStrategyPrice($price, $city_id, $this->site_id);
        return number_format($finalPrice, 2, '.', '');
    }

}
