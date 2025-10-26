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

namespace addon\home_service\app\service\api\store;

use addon\home_service\app\dict\store\StoreDict;
use addon\home_service\app\model\store\StoreApplication;
use addon\home_service\app\service\core\store\CoreStoreApplicationService;
use core\base\BaseApiService;
use addon\home_service\app\dict\technician\TechnicianDict;
use core\exception\ApiException;
use core\exception\CommonException;

/**
 * 门店入驻
 * Class TechnicianService
 * @package app\service\api\technician
 */
class StoreApplicationService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreApplication();
    }

    /**
     * 提交门店申请
     * @param array $data 申请数据
     * @return bool
     * @throws CommonException 当用户已是师傅时抛出异常
     */
    public function apply(array $data): bool
    {
        $storeApplicationInfo = $this->getInfo();
        if (!empty($storeApplicationInfo) && $storeApplicationInfo['audit_status'] == 0 ) throw new ApiException('HOME_SERVICE_NOT_REPEAT_APPLY');

        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['source'] = 'store';
        $data['audit_status'] = StoreDict::PENDING_EXAMINE;
        return (new CoreStoreApplicationService())->apply($data);
    }


    /**
     * 更新申请信息
     * @param int $id 申请id
     * @param array $data 待更新数据
     * @return bool|int 受影响的行数
     */
    public function update($id,$data)
    {
        $data['site_id'] = $this->site_id;
        return (new CoreStoreApplicationService())->update($id,$data);
    }


    /**
     * 获取门店申请信息
     * @return array
     */
    public function getInfo()
    {
        $field = 'id,store_name,contact_name,headimg,id_card_front,id_card_back,id_number,mobile,license_img,apply_desc,province_id,city_id,district_id,full_address,lng,lat,audit_remark,audit_status,create_time,audit_time';
        $info = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['source', '=', 'store']
        ])
            ->append(['audit_status_name'])
            ->field($field)->order('create_time desc')->findOrEmpty()->toArray();
        if(!empty($info)){
            $info['audit_time'] = date('Y-m-d H:i:s',$info['audit_time']);
        }

        return $info;
    }


}
