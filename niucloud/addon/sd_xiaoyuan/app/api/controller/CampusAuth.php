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
}
