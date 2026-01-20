<?php
namespace addon\tk_jhkd\app\api\controller;
use addon\tk_jhkd\app\service\core\TkjhkdBrandService;
use core\base\BaseController;
use think\Response;
class Brand extends BaseController
{
    /**
     * 快递品牌列表
     * @return Response
     */
    public function list()
    {
        return success((new TkjhkdBrandService())->getPage());
    }
}