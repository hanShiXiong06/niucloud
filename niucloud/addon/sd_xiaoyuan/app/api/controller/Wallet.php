<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\WalletService;
use core\base\BaseApiController;
use think\Response;

/**
 * 钱包接口
 */
class Wallet extends BaseApiController
{
    /**
     * 获取钱包余额
     */
    public function balance(): Response
    {
        $balance = (new WalletService())->getBalance();
        return success($balance);
    }

    /**
     * 获取流水列表
     */
    public function logList(): Response
    {
        $data = $this->request->params([
            ['type', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new WalletService())->getLogPage($data);
        return success($list);
    }
}
