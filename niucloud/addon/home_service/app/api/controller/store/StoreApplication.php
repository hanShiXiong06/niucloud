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

namespace addon\home_service\app\api\controller\store;

use addon\home_service\app\service\api\store\StoreApplicationService;
use core\base\BaseAdminController;


/**
 * 门店入驻
 * Class Reserve
 * @package app\adminapi\controller
 */
class StoreApplication extends BaseAdminController
{

    /**
     * 门店入驻
     * @return \think\Response
     */
    public function apply()
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["contact_name", ""],
            ["headimg", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ['mobile', ''],
            ['license_img', ''],
            ['apply_desc', ''],
            ["province_id", 0],
            ["city_id", 0],
            ["district_id", 0],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
        ]);
        (new StoreApplicationService())->apply($data);
        return success('SUCCESS');
    }

    /**
     * 门店申请编辑
     * @return Response
     */
    public function update($id)
    {
        $data = $this->request->params([
            ["store_name", ""],
            ["contact_name", ""],
            ["headimg", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ['mobile', ''],
            ['license_img', ''],
            ['apply_desc', ''],
            ["province_id", 0],
            ["city_id", 0],
            ["district_id", 0],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
        ]);
        return success('SUCCESS',(new StoreApplicationService())->update($id,$data));
    }

    /**
     * 门店申请详情
     * @param int $id
     * @return \think\Response
     */
    public function detail()
    {
        return success((new StoreApplicationService())->getInfo());
    }


}
