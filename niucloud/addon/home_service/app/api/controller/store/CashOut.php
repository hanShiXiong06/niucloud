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

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\service\api\store\CashOutService;
use app\dict\pay\TransferDict;
use core\base\BaseApiController;
use think\Response;

class CashOut extends BaseApiController
{

    /**
     * 会员提现列表
     * @return Response
     */
    public function lists()
    {
        $data = array_filter($this->request->params([
            ['status', ''],
            ['account_type', AccountDict::COMMISSION]
        ]), function ($value) {
            return $value !== '';
        });

        return success((new CashOutService())->getPage($data));
    }

    /**
     * 提现详情
     * @return Response
     */
    public function info($cash_id)
    {
        return success((new CashOutService())->getInfo($cash_id));
    }

    /**
     * 转账方式
     * @return \think\Response
     */
    public function getTransferType()
    {
        return success(TransferDict::getTransferType([], false));
    }

    /**
     * 申请提现
     * @return Response
     */
    public function apply()
    {
        $data = $this->request->params([
            ['apply_money', 0],
            ['account_type', AccountDict::COMMISSION],
            ['transfer_type', ''],
            ['account_id', 0],
            ['transfer_payee', []],//收款方信息
        ]);
//        $this->validate($data, 'app\validate\member\CashOut.apply');
        return success(data: (new CashOutService())->apply($data));
    }

    /**
     * 撤销提现申请
     * @param $id
     * @return Response
     */
    public function cancel($cash_id)
    {
        return success(data: (new CashOutService())->cancel($cash_id));
    }

    /**
     * 开始转账
     * @param $id
     * @return Response
     */
    public function transfer($id)
    {
        $data = $this->request->params([
            ['open_id', 0],
        ]);
        return success(data: (new CashOutService())->transfer($id, $data));
    }

}
