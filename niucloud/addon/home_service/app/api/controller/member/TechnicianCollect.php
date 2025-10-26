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

use addon\home_service\app\service\api\member\TechnicianCollectService;
use core\base\BaseApiController;


/**
 * 师傅收藏控制器
 * Class TechnicianCollect
 * @package addon\home_service\app\api\controller\member
 */
class TechnicianCollect extends BaseApiController
{

    /**
     * 获取商品收藏列表
     * @return \think\Response
     */
    public function getMemberTechnicianCollectList()
    {
        return success(( new TechnicianCollectService() )->getMemberTechnicianCollectList());
    }

    /**
     * 商品增加收藏
     */
    public function addTechnicianCollect()
    {
        $data = $this->request->params([
            [ 'technician_id', 0 ],
        ]);
        ( new TechnicianCollectService() )->addTechnicianCollect($data);
        return success('COLLECT_SUCCESS');
    }

    /**
     * 商品取消收藏
     */
    public function cancelTechnicianCollect()
    {
        $data = $this->request->params([
            [ 'technician_id', 0 ],
        ]);
        ( new TechnicianCollectService() )->cancelTechnicianCollect($data);
        return success('CANCEL_COLLECT_SUCCESS');
    }


}
