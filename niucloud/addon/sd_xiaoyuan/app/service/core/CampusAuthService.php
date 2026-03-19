<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\CampusAuth;
use core\base\BaseApiService;

class CampusAuthService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CampusAuth();
    }

    public function apply($data)
    {
        $exists = $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if (!$exists->isEmpty() && $exists['status'] == 1) {
            return ['code' => -1, 'msg' => '您已完成校园认证'];
        }

        if (!$exists->isEmpty() && $exists['status'] == 0) {
            return ['code' => -1, 'msg' => '您的认证申请正在审核中'];
        }

        // 如果已存在记录（被拒绝状态），更新现有记录
        if (!$exists->isEmpty()) {
            $updateData = [
                'real_name' => $data['real_name'] ?? '',
                'identity_type' => $data['identity_type'] ?? 'STUDENT',
                'school_id' => $data['school_id'] ?? 0,
                'campus' => $data['campus'] ?? '',
                'student_no' => $data['student_no'] ?? '',
                'college' => $data['college'] ?? '',
                'cert_image' => $data['cert_image'] ?? '',
                'status' => 0,
                'refuse_reason' => '',
                'audit_time' => 0,
                'update_time' => time()
            ];
            $this->model->where('id', $exists['id'])->update($updateData);
            return ['code' => 0, 'data' => $exists];
        }

        // 首次申请，创建新记录
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['status'] = 0;

        $res = $this->model->create($data);
        return ['code' => 0, 'data' => $res];
    }

    public function getAuthInfo()
    {
        $info = $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();
        
        if (!$info->isEmpty()) {
            $data = $info->toArray();
            // 关联学校表获取学校名称
            if ($data['school_id']) {
                $school = \addon\sd_xiaoyuan\app\model\School::where('id', $data['school_id'])->find();
                if ($school) {
                    $data['school_name'] = $school['name'];
                }
            }
            return $data;
        }
        
        return [];
    }

    public function checkAuth()
    {
        $info = $this->getAuthInfo();
        return !empty($info) && $info['status'] == 1;
    }
}
