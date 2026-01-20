<?php

namespace addon\tk_jhkd\app\adminapi\controller;

use addon\tk_jhkd\app\service\admin\agreement\AgreementService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 协议控制器
 */
class Agreement extends BaseAdminController
{
    /**
     * 协议列表
     * @return Response
     */
    public function lists()
    {
        $res = (new AgreementService())->getList();
        return success($res);
    }

    /**
     * 协议内容
     * @param string $key
     * @return Response
     */
    public function info(string $key)
    {
        $res = (new AgreementService())->getAgreement($key);
        return success($res);
    }

    /**
     * 协议更新
     * @param string $key
     * @return Response
     */
    public function edit(string $key)
    {
        $data = $this->request->params([
            ['title', ''],
            ['content', ''],
        ], false);
        $this->validate($data, 'app\validate\sys\Agreement.edit');
        (new AgreementService())->setAgreement($key, $data['title'], $data['content']);
        return success('EDIT_SUCCESS');
    }


}
