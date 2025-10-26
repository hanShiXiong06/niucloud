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
use addon\home_service\app\dict\help_feedback\HelpDict;
use addon\home_service\app\model\help_feedback\Feedback;
use addon\home_service\app\model\help_feedback\Help;
use addon\home_service\app\service\core\help_feedback\CoreFeedbackService;
use addon\home_service\app\service\core\help_feedback\CoreHelpService;
use core\base\BaseApiService;

/**
 *  反馈服务层
 * Class HelpService
 * @package app\service\api\help_feedback
 */
class HelpService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Help();
    }

    /**
     * 获取帮助列表
     * @param array $data
     * @return bool
     */
    public function getHelpPage(array $data = [])
    {
        $data['type'] = HelpDict::TECHNICIAN;
        return (new CoreHelpService())->getHelpPage($data);
    }

    /**
     * 获取帮助详情
     * @param array $data
     * @return bool
     */
    public function getHelpInfo(array $data = [])
    {
        $data['type'] = HelpDict::TECHNICIAN;
        return (new CoreHelpService())->getHelpInfo($data);
    }

}
