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

namespace addon\home_service\app\adminapi\controller\settlement;

use addon\home_service\app\service\admin\settlement\StoreSettlementService;
use core\base\BaseApiController;
use think\Response;

class StoreSettlement extends BaseApiController
{

    /**
     * 门店结算列表
     * @return Response
     */
    public function page()
    {
        $data =$this->request->params([
            ['store_name', ''],
        ]);
        return success((new StoreSettlementService())->getPage($data));
    }

    /**
     * 门店结算账单列表
     * @return Response
     */
    public function accountPage()
    {
        $data =$this->request->params([
            ['store_id', ''],
            ['month', ''],
            ['order_no', ''],
        ]);
        return success((new StoreSettlementService())->getAccountPage($data));
    }

    /**
     * 门店结算统计
     * @return Response
     */
    public function settlementStat()
    {
        $data =$this->request->params([
            ['store_name', ''],
        ]);
        return success((new StoreSettlementService())->getSettlementStat($data));
    }

}
