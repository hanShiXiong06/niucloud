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

namespace addon\phone_shop\app\listener\marketing;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\job\marketing\NewcomerSaveAfter;
use addon\phone_shop\app\service\admin\marketing\DiscountService;
use think\facade\Log;

/**
 * 活动保存后事件
 * Class ShopActiveSaveAfter
 * @package addon\phone_shop\app\listener\marketing
 */
class ShopActiveSaveAfter
{
    public function handle(array $params)
    {
        $active_info = $params;
        Log::write('ShopActiveSaveAfter:' . json_encode($active_info));
        if($active_info['active_class'] == ActiveDict::NEWCOMER_DISCOUNT && $active_info['active_status'] == ActiveDict::ACTIVE) {
            NewcomerSaveAfter::dispatch([ 'site_id' => $active_info['site_id'] ]);
        }
        return true;
    }
}
