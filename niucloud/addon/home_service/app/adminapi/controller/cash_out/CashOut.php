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

namespace addon\home_service\app\adminapi\controller\cash_out;

use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\service\admin\cash_out\CashOutService;
use app\dict\pay\TransferDict;
use core\base\BaseApiController;
use think\Response;

class CashOut extends BaseApiController
{

    /**
     * 提现列表
     * @return Response
     */
    public function page()
    {
        $data =$this->request->params([
            ['status', ''],
            ['keywords', ''],
            ['source', CashOutDict::TECHNICIAN]
        ]);
        return success((new CashOutService())->getPage($data));
    }

    /**
     * 提现详情
     * @return Response
     */
    public function info($cash_out_id)
    {
        return success((new CashOutService())->getInfo($cash_out_id));
    }

    /**
     * 转账方式
     * @description 转账方式
     * @return Response
     */
    public function getTransferType()
    {
        return success(TransferDict::getTransferType([], false));
    }

    /**
     * 转账方式
     * @description 转账方式
     * @param $cash_out_id
     * @return Response
     */
    public function transfer($cash_out_id)
    {
        $data = $this->request->params([
            ['transfer_voucher', ''],
            ['transfer_remark', ''],
            ['transfer_type', '']
        ]);
        (new CashOutService())->transfer($cash_out_id, $data);
        return success();
    }

    /**
     * 备注转账信息
     * @description 备注转账信息
     * @param $cash_out_id
     * @return Response
     */
    public function remark($cash_out_id)
    {
        $data = $this->request->params([
            ['remark', ''],
        ]);
        (new CashOutService())->remark($cash_out_id, $data);
        return success();
    }
    /**
     * 状态
     * @description 状态
     * @return Response
     */
    public function getStatusList()
    {
        return success(CashOutDict::getStatus());
    }

    /**
     * 统计数据
     * @description 统计数据
     */
    public function stat()
    {
        return success((new CashOutService())->stat());
    }

    /**
     * 校验数组是否
     * @description 校验数组是否
     * @return Response
     */
    public function checkTransferStatus($id){
        (new CashOutService())->checkTransferStatus($id);
        return success();
    }

    /**
     * 取消
     * @description 取消
     * @param $cash_out_id
     * @return Response
     */
    public function cancel($cash_out_id){

        (new CashOutService())->cancel($cash_out_id);
        return success();
    }

}
