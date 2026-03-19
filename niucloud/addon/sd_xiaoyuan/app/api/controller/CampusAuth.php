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
        
        // 先检查是否需要实名认证
        $configService = new \addon\sd_xiaoyuan\app\service\core\ConfigService();
        $config = $configService->getConfig();
        
        // 如果关闭了实名认证要求，直接返回通过
        if (empty($config['require_auth_publish'])) {
            return success(['is_auth' => true]);
        }
        
        // 否则正常检查认证状态
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
        
        // 先检查是否需要实名认证
        $configService = new \addon\sd_xiaoyuan\app\service\core\ConfigService();
        $config = $configService->getConfig();
        
        // 如果关闭了实名认证要求，直接返回已认证状态
        if (empty($config['require_auth_publish'])) {
            return success([
                'status' => 2,  // 2=已认证
                'message' => '已认证'
            ]);
        }
        
        $info = $service->getAuthInfo();
        
        // 如果没有认证记录，返回未认证状态
        if (empty($info)) {
            return success([
                'status' => 0,  // 0=未认证
                'message' => '未提交认证申请'
            ]);
        }
        
        // 返回认证状态
        // status: 0=审核中, 1=已认证, -1=审核拒绝
        return success([
            'status' => $info['status'] == 1 ? 2 : ($info['status'] == -1 ? -1 : ($info['status'] == 0 ? 1 : 0)),
            'message' => $info['status'] == 1 ? '已认证' : ($info['status'] == -1 ? '审核拒绝' : ($info['status'] == 0 ? '审核中' : '未认证')),
            'refuse_reason' => $info['refuse_reason'] ?? ''
        ]);
    }
}
