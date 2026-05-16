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

namespace addon\recycle\app\api\controller\recycle_category;

use addon\recycle\app\service\api\recycle_category\RecycleBannerService;
use core\base\BaseApiController;

/**
 * Banner前端控制器
 * Class RecycleBanner
 * @package addon\recycle\app\api\controller\recycle_category
 */
class RecycleBanner extends BaseApiController
{
    /**
     * 获取Banner列表
     * @return \think\Response
     */
    public function lists()
    {
        return success((new RecycleBannerService())->getPage());
    }
}
