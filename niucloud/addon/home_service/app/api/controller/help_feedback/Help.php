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

namespace addon\home_service\app\api\controller\help_feedback;

use addon\home_service\app\service\api\help_feedback\HelpService;
use core\base\BaseApiController;


/**
 *  商品分类控制器
 * Class Help
 * @description  商品分类
 * @package app\adminapi\controller\help
 */
class Help extends BaseApiController
{

    /**
     * 获取帮助列表
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function page()
    {
        $data = $this->request->params([
            ["name", ""],
            ["type", ""],
            ["category_id", ""],
        ]);
        return success((new HelpService())->getHelpPage($data));
    }

    /**
     * 获取帮助详情
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function info()
    {
        $data = $this->request->params([
            ["help_id", ""],
        ]);
        return success('SUCCESS',(new HelpService())->getHelpInfo($data));
    }



}
