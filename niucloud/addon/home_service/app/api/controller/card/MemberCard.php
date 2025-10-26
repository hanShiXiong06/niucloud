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

namespace addon\home_service\app\api\controller\card;

use addon\home_service\app\service\api\card\CardService;
use addon\home_service\app\service\api\card\MemberCardService;
use core\base\BaseApiController;


/**
 * 会员次卡控制器
 * Class MemberCard
 * @package addon\home_service\app\api\controller\card
 */
class MemberCard extends BaseApiController
{
    /**
     * 状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', (new MemberCardService())->getStatus());
    }

    /**
     * 获取会员次卡列表
     * @description 获取次卡项目列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["status", ""],
        ]);
        return success((new MemberCardService())->getPage($data));
    }

    /**
     * 会员次卡项目列表
     * @description 会员次卡项目列表
     * @return \think\Response
     */
    public function item()
    {
        $data = $this->request->params([
            ["member_card_id", 0],
        ]);
        return success((new MemberCardService())->getItem($data));
    }

    /**
     * 会员次卡使用记录
     * @description 会员次卡项目列表
     * @return \think\Response
     */
    public function getCardUseRecords()
    {
        $data = $this->request->params([
            ["member_card_id", 0],
        ]);
        return success((new MemberCardService())->getCardUseRecords($data));
    }
}
