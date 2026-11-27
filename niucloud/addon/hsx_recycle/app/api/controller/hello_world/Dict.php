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

namespace addon\hsx_recycle\app\api\controller\hello_world;

use core\base\BaseApiController;
use app\service\admin\dict\DictService;
use think\Response;

class Dict extends BaseApiController
{
    /**
     * Hello World
     * @return Response
     */
    public function getDict($id)
    {

            return success((new DictService())->getInfo($id));

    
    }
}

