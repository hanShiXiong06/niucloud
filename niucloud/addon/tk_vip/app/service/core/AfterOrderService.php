<?php

namespace addon\tk_vip\app\service\core;

use addon\tk_vip\app\dict\config\ConfigDict;
use addon\tk_vip\app\model\fenxiao\FenxiaoOrder;
use app\service\core\site\CoreSiteService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use think\facade\Db;

/**
 * 配置信息服务层
 * Class ConfigService
 * @package addon\tk_jhkd\service\core\config
 */
class AfterOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * @Notes:订单支付后进行操作
     * @Interface paySuccess
     * @param $orderInfo
     * @author: TK
     * @Time: 2025/7/3   07:22
     */

    public function paySuccess($orderInfo)
    {
        $site_id = $orderInfo['site_id'];
        $member_id = $orderInfo['member_id'];
        //进行商城关联分销处理
        //进行分销入库处理 1、判断是否开启分销 2、自购处理 3、分销处理
    }
}