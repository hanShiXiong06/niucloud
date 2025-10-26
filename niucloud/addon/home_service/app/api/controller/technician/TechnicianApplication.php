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

use addon\home_service\app\service\api\technician\TechnicianApplicationService;
use core\base\BaseApiController;


/**
 * 师傅入驻业务
 * Class Reserve
 * @package app\adminapi\controller
 */
class TechnicianApplication extends BaseApiController
{

    /**
     * 师傅入驻
     * @param int $id
     * @return \think\Response
     */
    public function apply()
    {
        $data = $this->request->params([
            ["real_name", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ['mobile', ''],
            ['certificate', ''],
            ['notes', ''],
            ["province_id", 0],
            ["city_id", 0],
            ["district_id", 0],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
            ["store_id", 0],
            ["headimg", ''],
            ["category_id", ''],
        ]);
        return success((new TechnicianApplicationService())->apply($data));
    }

    /**
     * 师傅详情
     * @param int $id
     * @return \think\Response
     */
    public function detail()
    {
        return success((new TechnicianApplicationService())->getInfo());
    }


}
