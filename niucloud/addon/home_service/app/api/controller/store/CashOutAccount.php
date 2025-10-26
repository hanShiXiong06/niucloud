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

use addon\home_service\app\service\api\store\CashOutAccountService;
use core\base\BaseApiController;
use think\Response;

class CashOutAccount extends BaseApiController
{
    /**
     * 提现账户列表
     * @return Response
     */
    public function lists(){
        $data = $this->request->params([
            ['account_type', '']
        ]);
        return success((new CashOutAccountService())->getPage($data));
    }

    /**
     * 提现账户信息
     * @param int $account_id
     * @return Response
     */
    public function info(int $account_id){
        return success((new CashOutAccountService())->getInfo($account_id));
    }

    /**
     * 查询首条提现账户按账户类型
     * @return Response
     */
    public function firstInfo(){
        $data = $this->request->params([
            ['account_type', '']
        ]);
        return success((new CashOutAccountService())->getFirstInfo($data));
    }

    /**
     * 添加提现账号
     * @return Response
     */
    public function add(){
        $data = $this->request->params([
            ['account_type', ''],
            ['bank_name', ''],
            ['realname', ''],
            ['account_no', ''],
            ['transfer_payment_code', '']
        ]);
        $this->validate($data, 'addon\home_service\app\validate\cash_out\CashOutAccount.addOrEdit');
        $id = (new CashOutAccountService())->add($data);
        return success('ADD_SUCCESS', [ 'id' => $id ]);
    }

    /**
     * 编辑提现账号
     * @param int $account_id
     * @return Response
     */
    public function edit(int $account_id){
        $data = $this->request->params([
            ['account_type', ''],
            ['bank_name', ''],
            ['realname', ''],
            ['account_no', ''],
            ['transfer_payment_code', '']
        ]);
        $this->validate($data, 'addon\home_service\app\validate\cash_out\CashOutAccount.addOrEdit');
        (new CashOutAccountService())->edit($account_id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除提现账号
     * @param int $account_id
     * @return Response
     */
    public function del(int $account_id){
        (new CashOutAccountService())->del($account_id);
        return success('DELETE_SUCCESS');
    }
}
