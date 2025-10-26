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

use addon\home_service\app\service\api\technician\TechnicianSearch  as TechnicianSearchService;
use core\base\BaseApiController;


/**
 * 师傅休息控制器
 * @description 师傅休息
 * Class Reserve
 * @package app\api\controller\technician
 */
class TechnicianSearch extends BaseApiController
{
    /**
     * 获取师傅休息记录
     * @description 获取师傅休息记录
     * @return \think\Response
     */
    public function getTechnicianSearchList()
    {
        $data = $this->request->params([
            ['lng', ''],
            ['lat', ''],
            ['distance', 'all'],
            ['goods_id', ''],
            ['goods_id', ''],
        ]);
        return success('SUCCESS',(new TechnicianSearchService())->getTechnicianSearchList($data));
    }


}
