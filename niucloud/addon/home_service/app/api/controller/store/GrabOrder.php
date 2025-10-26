<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\api\controller\store;

use addon\home_service\app\dict\order\GrabOrderDict;
use addon\home_service\app\service\api\store\GrabOrderService;
use core\base\BaseAdminController;


/**
 * 门店抢单大厅
 * Class Reserve
 * @package app\adminapi\controller
 */
class GrabOrder extends BaseAdminController
{


    /**
     * 获取抢单分类数据 + 统计
     * @return \think\Response
     */
    public function grabCategory()
    {
        return success('SUCCESS', (new GrabOrderService())->grabCategory());
    }

    /**
     * 获取抢单分类弹窗 距离筛选
     * @return \think\Response
     */
    public function getGrabdistance()
    {
        return success('SUCCESS', (GrabOrderDict::getDistance()));
    }

    /**
     * 获取抢单大厅
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_status', ''],
            ['category_id', ''],
            ['lng', ''],
            ['lat', ''],
            ['distance', 'all'],
        ]);
        return success('SUCCESS', (new GrabOrderService())->getPage($data));
    }

    /**
     * 获取抢单大厅详情
     * @param $order_id
     * @return \think\Response
     */
    public function detail($order_id)
    {
        return success('SUCCESS', (new GrabOrderService())->getDetail($order_id));
    }

    /**
     * 抢单
     * @return \think\Response
     */
    public function grab($id)
    {
        return success('SUCCESS', (new GrabOrderService())->grab($id));
    }


}
