<?php

namespace addon\phone_shop\app\api\controller\member;

use addon\phone_shop\app\service\api\member\ForwardApplicationService;
use core\base\BaseApiController;

class ForwardApplication extends BaseApiController
{
    public function access()
    {
        return success((new ForwardApplicationService())->access());
    }

    public function apply()
    {
        $data = $this->request->params([
            ['form_record_id', 0],
            ['message', ''],
        ]);
        $id = (new ForwardApplicationService())->apply((int)$data['form_record_id'], (string)$data['message']);
        return success('申请已提交，审核结果会及时通知您', ['application_id' => $id]);
    }
}
