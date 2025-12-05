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
use addon\phone_shop\app\service\admin\marketing\DiscountService;
use think\facade\Log;

/**
 * 限时折扣
 * Class DiscountActiveEndAfter
 * @package addon\phone_shop\app\listener
 */
class DiscountActiveEndAfter
{
    public function handle(array $params)
    {
        $active_info = $params;
        Log::write('DiscountActiveEndAfter:' . json_encode($active_info));
        if (!empty($active_info) && $active_info[ 'active_class' ] == ActiveDict::DISCOUNT) {
            ( new DiscountService() )->discountEndAfter($active_info[ 'active_id' ]);
        }
        return true;
    }
}
