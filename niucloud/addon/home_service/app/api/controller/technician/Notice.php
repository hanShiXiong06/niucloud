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

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\service\api\technician\NoticeService;
use core\base\BaseApiController;


/**
 * 通知控制器
 * Class Notice
 * @package app\api\controller
 */
class Notice extends BaseApiController
{

    /**
     * 获取通知信息
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["notice_source", "system" ],
        ]);
        return success((new NoticeService())->getPage($data));
    }

    /**
     * 消息来源
     * @return array|array[]|string
     */
    public function getNoticeSource()
    {
        return success((new NoticeService())->getNoticeSource());


    }

}
