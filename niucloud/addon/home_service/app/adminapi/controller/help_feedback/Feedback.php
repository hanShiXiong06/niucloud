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

namespace addon\home_service\app\adminapi\controller\help_feedback;

use addon\home_service\app\dict\help_feedback\FeedbackDict;
use addon\home_service\app\service\admin\help_feedback\FeedbackService;
use core\base\BaseAdminController;


/**
 *  反馈控制器
 * Class Feedback
 * @description  反馈
 * @package app\adminapi\controller\help
 */
class Feedback extends BaseAdminController
{

    /**
     * 获取反馈来源
     * @description 获取帮助类型
     * @return \think\Response
     */
    public function source()
    {
        return success(FeedbackDict::getSource());
    }

    /**
     * 获取反馈列表
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function page()
    {
        $data = $this->request->params([
            ["title", ""],
            ["source", ""],
        ]);
        return success((new FeedbackService())->getPage($data));
    }

}
