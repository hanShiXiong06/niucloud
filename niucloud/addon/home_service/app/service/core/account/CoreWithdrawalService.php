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

namespace addon\o2o\app\service\core\account;

use addon\baofu\app\dict\customer\CustomerDict;
use addon\baofu\app\service\core\baofu_sdk\customer\CoreAccountBalanceQueryService;
use addon\baofu\app\service\core\CoreCustomerService;
use addon\o2o\app\dict\account\AccountDict;
use addon\o2o\app\model\O2oShop;
use app\dict\sys\AppTypeDict;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\Model;
use addon\o2o\app\model\ShopAccountLog as ShopAccountLog;
use addon\baofu\app\model\customer\BaofuCustomer;


/**
 * 取消业务
 */
class CoreWithdrawalService extends BaseCoreService
{


    /**
     * 申请提现
     */
    public function withdrawal($data)
    {
        Db::startTrans();
        try {
            $customer_info = (new CoreCustomerService())->getInfo((new BaofuCustomer()), [['main_id', '=', $data['main_id']], ['main_type', '=', $data['main_type']]]);
            if (empty($customer_info)) throw new CommonException('请开会后在尝试此操作');

            $dataQuery['contract_no'] = $customer_info['contract_no'];
            $dataQuery['acc_type'] = $customer_info['acc_type'];
            $resp_data = (new CoreAccountBalanceQueryService())->execute($dataQuery);
            if ($resp_data['body']['retCode'] != CustomerDict::RET_CODE_SUCCESS) throw new CommonException($resp_data['body']['errorMsg']);
            $bao_fu_accinfo = $resp_data['body'];
            if ($bao_fu_accinfo['availableBal'] < $data['deal_amount']) {
                throw new CommonException('宝付账户中可提现金额不足!!!');
            }
            // 定义处理逻辑映射关系
            $handlerMap = [
                AppTypeDict::TECHNICIAN => CoreTechnicianAccountService::class,
                AppTypeDict::SHOPAPP => CoreShopAccountService::class,
                AppTypeDict::ADMIN => CoreSystemAccountService::class,
                AppTypeDict::SITE => CoreSystemAccountService::class, // 假设品牌商
            ];
            // 根据类型获取对应服务类
            if (!isset($handlerMap[$data['main_type']])) {
                throw new CommonException('不支持的账户类型');
            }
            // 创建服务实例
            $serviceClass = $handlerMap[$data['main_type']];
            $service = new $serviceClass();
            $res = (new CoreCustomerService)->accountWithdrawal($data);
            $related_id = $res['body']['transSerialNo'] ?? '';
            log2(
                [
                    'data' => $data,
                    'res' => $res,
                ], "CoreWithdrawalService_withdrawal"
            );
            //            // 定义处理逻辑映射关系
            $data['related_id'] = $related_id;
            // 执行提现操作
            $res = $service->withdrawal($data);
            Db::commit();
            return $res;
        } catch (\Exception $e) {
            Db::rollback();
            log2([
                'message' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTrace()
            ], "CoreWithdrawalService_withdrawal_error");
            throw new CommonException($e->getMessage());
        }
    }


}
