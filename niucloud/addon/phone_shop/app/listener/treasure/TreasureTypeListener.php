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

namespace addon\phone_shop\app\listener\treasure;

/**
 * 宝贝类型查询
 * Class TreasureTypeListener
 * @package addon\phone_shop\app\listener\treasure
 */
class TreasureTypeListener
{

    public function handle()
    {
        return [
            'phone_shop' => '商品'
        ];
    }
}