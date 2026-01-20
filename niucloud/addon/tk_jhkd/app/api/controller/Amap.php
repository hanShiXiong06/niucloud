<?php
namespace addon\tk_jhkd\app\api\controller;
use core\base\BaseController;
use addon\tk_jhkd\app\service\core\AmapService;
use think\Response;
class Amap extends BaseController
{
    /**
     *地理位置逆向编码
     */
    public function regeo($value)
    {
        $data=$this->request->params([
            ["value","121.4948,31.2235"],
        ]);
        return success((new AmapService())->regeo($data['value']));
    }
}