<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------


namespace addon\home_service\app\listener\technician;

use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\model\technician\TechnicianApplication;


/**
 * 师傅 添加
 * Class MemberAccount
 * @package app\listener\member
 */
class AddHsTechnician
{
    public function handle(array $data)
    {
        $field = 'audit_time,create_time,headimg,category_id,store_id,audit_remark,audit_status,real_name,id_card_front,id_card_back,id_number,mobile,certificate,notes,province_id,city_id,district_id,full_address,lng,lat';
        // 查询是否已存在该师傅
        $info = (new TechnicianApplication)->where([['member_id', '=', $data['member_id']], ['site_id', '=', $data['site_id']]])->append(['audit_status_name'])->field($field)->findOrEmpty()->toArray();
        // 如果是新师傅（不存在），则组装数据
        if (empty($info)) {
            // 定义需要的字段及默认值
            $defaultData = [
                "real_name" => "",
                "id_card_front" => "",
                "id_card_back" => "",
                "id_number" => "",
                "mobile" => "",
                "certificate" => "",
                "notes" => "",
                "province_id" => 0,
                "city_id" => 0,
                "district_id" => 0,
                "full_address" => "",
                "lng" => "",
                "lat" => "",
                "store_id" => 0,
                "headimg" => "",
                "category_id" => "",
                "audit_status" => TechnicianDict::PASS, // 默认审核通过
                "audit_time" => time(), // 默认当前时间
                // 补充上下文关联字段（如果需要）
                "member_id" => $data['member_id'], // 从传入数据获取
                "site_id" => $data['site_id'] // 从传入数据获取
            ];
            // 合并数据：$data 中存在的字段覆盖默认值，缺失的用默认值
            $applyData = array_merge($defaultData, array_intersect_key($data, $defaultData));
            // 执行添加操作（示例，根据实际模型调整）
            (new TechnicianApplication)->create($applyData);
        }
        return true;
    }


}
