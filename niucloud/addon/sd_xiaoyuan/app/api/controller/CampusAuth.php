<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\CampusAuthService;
use core\base\BaseApiController;

class CampusAuth extends BaseApiController
{
    public function info()
    {
        $service = new CampusAuthService();
        $info = $service->getAuthInfo();
        return success($info);
    }

    public function apply()
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['campus', ''],
            ['real_name', ''],
            ['student_no', ''],
            ['college', ''],
            ['identity_type', 'STUDENT'],
            ['cert_image', '']
        ]);
        
        $service = new CampusAuthService();
        $result = $service->apply($data);
        
        if ($result['code'] != 0) {
            return fail($result['msg']);
        }
        
        return success('提交成功，请等待审核');
    }

    public function checkAuth()
    {
        $service = new CampusAuthService();
        $isAuth = $service->checkAuth();
        return success(['is_auth' => $isAuth]);
    }

    /**
     * 获取认证状态
     * 返回详细的认证状态信息
     */
    public function status()
    {
        $service = new CampusAuthService();
        $info = $service->getAuthInfo();

        // 如果没有认证记录，返回未认证状态
        if (empty($info)) {
            return success([
                'status' => 0,
                'message' => '未提交认证申请',
                'refuse_reason' => ''
            ]);
        }

        // 状态映射：数据库状态 -> API 返回状态
        // 数据库: -1=拒绝, 0=审核中, 1=已认证
        // API: 0=未认证, 1=审核中, 2=已认证, -1=拒绝
        $statusMap = [
            -1 => ['status' => -1, 'message' => '审核拒绝'],
            0  => ['status' => 1, 'message' => '审核中'],
            1  => ['status' => 2, 'message' => '已认证']
        ];

        $result = $statusMap[$info['status']] ?? ['status' => 0, 'message' => '未认证'];
        $result['refuse_reason'] = $info['refuse_reason'] ?? '';

        return success($result);
    }
}
