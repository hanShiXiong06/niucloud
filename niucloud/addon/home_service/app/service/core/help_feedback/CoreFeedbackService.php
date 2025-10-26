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

namespace addon\home_service\app\service\core\help_feedback;


use addon\home_service\app\model\help_feedback\Feedback;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 反馈服务层
 * Class CoreFeedbackService
 * @package addon\o2o\app\service\api\help_feedback
 */
class CoreFeedbackService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Feedback();
    }

    /**
     * 添加反馈信息
     * @param $data
     */
    public function addFeedback($data)
    {
        $this->model->create($data);
        return true;
    }


}
