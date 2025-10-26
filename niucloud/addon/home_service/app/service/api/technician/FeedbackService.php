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

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\dict\help_feedback\FeedbackDict;
use addon\home_service\app\model\help_feedback\Feedback;
use addon\home_service\app\service\core\help_feedback\CoreFeedbackService;
use core\base\BaseApiService;

/**
 *  反馈服务层
 * Class FeedbackService
 * @package app\service\api\technician
 */
class FeedbackService extends BaseApiService
{
    use TechnicianTrait;
    public function __construct()
    {
        parent::__construct();
        $this->model = new Feedback();
        $this->checkTechnician();
    }

    /**
     * 添加反馈信息
     * @param array $data
     * @return bool
     */
    public function add(array $data = [])
    {
        $data['site_id'] = $this->site_id;
        $data['source'] = FeedbackDict::TECHNICIAN;
        $data['related_id'] = $this->technician_id;
        return (new CoreFeedbackService())->addFeedback($data);
    }


}
