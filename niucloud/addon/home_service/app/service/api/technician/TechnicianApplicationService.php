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

use addon\home_service\app\model\technician\TechnicianApplication;
use core\base\BaseApiService;
use addon\home_service\app\dict\technician\TechnicianDict;
use core\exception\CommonException;

/**
 * 师傅入驻
 * Class TechnicianService
 * @package app\service\api\technician
 */
class TechnicianApplicationService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianApplication();
    }


    /**
     * 提交师傅申请
     * @param array $data 申请数据
     * @return bool
     * @throws CommonException 当用户已是师傅时抛出异常
     */
    public function apply(array $data): bool
    {
        // 检查用户是否已是师傅
        $existingTechnicianInfo = (new TechnicianService())->checkTechnician();
        if (!empty($existingTechnicianInfo)) {
            throw new CommonException('HOME_SERVICE_YOU_ARE_ALREADY_TECHNICIAN_NO_NEED_APPLY');
        }
        if (empty($data['category_id'])) throw new CommonException('HOME_SERVICE_TECHNICIAN_CATEGORY_id_NOT_EXIST');
        // 设置申请状态为待审核
        $data['audit_status'] = TechnicianDict::PENDING_EXAMINE;
        // 获取现有申请信息
        $existingApplicationInfo = $this->getInfo();
        // 处理申请：新建或更新
        if (empty($existingApplicationInfo)) {
            $data['create_time'] = time();
            $data['site_id'] = $this->site_id;
            $data['member_id'] = $this->member_id;
            // 无历史申请，创建新申请
            $this->model->create($data);
        } elseif ($existingApplicationInfo['audit_status'] != TechnicianDict::PASS) {
            // 有未通过的历史申请，更新申请信息  audit_remark
            $data['audit_remark'] = '';
            $this->update($data);
        }
        return true;
    }


    /**
     * 更新申请信息
     * @param array $data 待更新数据
     * @return bool|int 受影响的行数
     */
    public function update(array $data)
    {
        return $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->update($data);
    }


    /**
     * 获取信息
     * @param int $id
     * @return array
     */
    public function getInfo()
    {
        $field = 'audit_time,create_time,headimg,category_id,store_id,audit_remark,audit_status,real_name,id_card_front,id_card_back,id_number,mobile,mobile,certificate,notes,province_id,city_id,district_id,full_address,lng,lat';
        $info = $this->model->where([['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])
            ->append(['audit_status_name'])
            ->field($field)->findOrEmpty()->toArray();
        return $info;
    }


}
