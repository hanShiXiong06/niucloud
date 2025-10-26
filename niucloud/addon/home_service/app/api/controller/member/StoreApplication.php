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

use addon\home_service\app\service\api\member\StoreApplicationService;
use core\base\BaseApiController;
use think\Response;

class StoreApplication extends BaseApiController
{

    /**
     * 获取用户门店申请信息
     * @return Response
     */
    public function getStoreApplicationInfo()
    {
        return success('SUCCESS',(new StoreApplicationService())->getStoreApplicationInfo());
    }

    /**
     * 门店申请
     * @return Response
     */
    public function StoreApply()
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
        return success('SUCCESS',(new StoreApplicationService())->StoreApply($data));
    }

    /**
     * 门店申请编辑
     * @return Response
     */
    public function StoreApplyEdit($id)
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
        return success('SUCCESS',(new StoreApplicationService())->StoreApplyEdit($id,$data));
    }


}
