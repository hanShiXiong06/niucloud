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

namespace addon\home_service\app\adminapi\controller\replace_buy;

use addon\home_service\app\service\admin\replace_buy\OrderCreateService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 订单创建
 * Class OrderCreate
 * @description 换购订单创建
 * @package addon\home_service\app\adminapi\controller
 */
class OrderCreate extends BaseAdminController
{

    /**
     * 已选商品计算
     * @description 已选商品计算
     * @return Response
     */
    public function selectGoodsCalculate()
    {
        $data = $this->request->params([
            [ 'sku_ids', [] ],
            [ 'member_id', 0 ],
        ]);
        return success(( new OrderCreateService() )->selectGoodsCalculate($data));
    }

    /**
     * 计算
     * @description 计算
     * @return Response
     */
    public function calculate()
    {
        $data = $this->request->params([
            ['sku', ''],
            ['delivery', []],
            ['reserve_service_time', ''],
            ['card_data', []],

            ['order_key', []],
            ['discount', []],//优惠
            ['member_id', 0],
        ]);
        $data['sku'] = json_decode($data['sku'], true);

        return success('SUCCESS', ( new OrderCreateService() )->calculate($data));
    }

    /**
     * 订单创建
     * @description 订单创建
     * @return Response
     */
    public function create()
    {
        $data = $this->request->params([
            ['member_id', ''],
            ['order_key', ''],
            ['member_remark', ''],
            ['reserve_service_time', ''],
            ['reserve_service_time_stamp', '']
        ]);
        return success('SUCCESS', ( new OrderCreateService() )->create($data));
    }

    /**
     * 查询优惠券
     * @description 查询优惠券
     * @return Response
     */
    public function getCoupon()
    {
        $data = $this->request->params([
            [ 'order_key', [] ],
            [ 'member_id', '' ],
        ]);
        return success('SUCCESS', ( new OrderCreateService() )->getCoupon($data));
    }

    /**
     * 获取自提点
     * @description 获取自提点
     * @return Response
     */
    public function getStore()
    {
        $data = $this->request->params([
            [ 'latlng', [] ],
        ]);
        return success('SUCCESS', ( new OrderCreateService() )->getStore($data[ 'latlng' ]));
    }

    /**
     * 检测订单支付
     * @description 检测订单支付
     * @return Response
     */
    public function checkPay()
    {
        $data = $this->request->params([
            [ 'trade_id', 0 ],
            [ 'trade_type', '' ],
        ]);
        return success('SUCCESS', ( new OrderCreateService() )->checkPay($data));
    }

}
