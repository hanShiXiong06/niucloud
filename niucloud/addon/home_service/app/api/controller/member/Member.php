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

namespace addon\home_service\app\api\controller\member;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\service\api\member\MemberService;
use addon\home_service\app\service\api\store\CashOutService;
use app\dict\pay\TransferDict;
use core\base\BaseApiController;
use think\Response;

class Member extends BaseApiController
{

    /**
     * 会员优惠项数量
     * @return Response
     */
    public function MemberDiscountCount()
    {
        return success((new MemberService())->MemberDiscountCount());
    }


}
