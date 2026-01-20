<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\adminapi\controller;

use addon\tk_jhkd\app\service\core\JhkdService;
use core\base\BaseAdminController;
class Jhkd extends BaseAdminController
{
    /**
     * 获取快递品牌列表
     */
    public function getBrand()
    {
        return success("TK_JHKD_SUCCESS",(new JhkdService())->getBrand());
    }
    public function setConfig(){
        $data = $this->request->params([
            ['username', ''],
            ['privateKey', ''],
            ['floatWay', ''],
            ['floatAmount', ''],
            ['floatRate', ''],
        ]);
        (new ConfigService())->setConfig($data);
        return success('TK_JHKD_SUCCESS');
    }

}
