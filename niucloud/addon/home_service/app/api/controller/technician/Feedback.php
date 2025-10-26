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

namespace addon\home_service\app\api\controller\technician;

use addon\home_service\app\service\api\technician\FeedbackService;
use core\base\BaseApiController;


/**
 *  反馈控制器
 * Class Feedback
 * @description  反馈
 * @package app\api\controller\technician
 */
class Feedback extends BaseApiController
{
    /**
     * 添加反馈信息
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["title", ""],
            ["images", ""],
            ["content", ""],
        ]);
        return success((new FeedbackService())->add($data));
    }

}
