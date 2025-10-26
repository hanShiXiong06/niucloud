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

namespace addon\home_service\app\api\controller\order;

use addon\home_service\app\service\api\order\OrderCreateService;
use core\base\BaseApiController;
use think\Response;

class OrderCreate extends BaseApiController
{

    /**
     * 订单确认
     * @return \think\Response
     */
    public function confirm()
    {
        $data = $this->request->params([
            ['sku', '[]'],
        ]);
        $data['sku'] = json_decode($data['sku'], true);
        $this->validate($data, 'addon\home_service\app\validate\Order.confirm');
        return success('SUCCESS', (new OrderCreateService())->confirm($data));
    }

    /**
     * 订单计算
     * @return void
     */
    public function calculate()
    {
        $data = $this->request->params([
            ['sku', '[]'],
            ['delivery', []],
            ['reserve_service_time', ''],

            ['order_key', []],
            ['discount', []],//优惠
            ['card_data', []],
        ]);
        $data['sku'] = json_decode($data['sku'], true);
        $this->validate($data, 'addon\home_service\app\validate\Order.calculate');
        return success('SUCCESS', (new OrderCreateService())->calculate($data));
    }

    /**
     * 订单创建
     * @return void
     */
    public function create()
    {
        $data = $this->request->params([
            ['order_key', ''],
            ['member_remark', ''],
            ['reserve_service_time', ''],
            ['reserve_service_time_stamp', '']
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Order.create');
        return success('SUCCESS', (new OrderCreateService())->create($data));
    }

    /**
     * 查询优惠券
     * @return Response
     */
    public function getCoupon()
    {
        $data = $this->request->params([
            ['order_key', []],
        ]);
        return success('SUCCESS', (new OrderCreateService())->getCoupon($data));
    }


}
