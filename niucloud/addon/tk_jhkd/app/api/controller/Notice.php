<?php
namespace addon\tk_jhkd\app\api\controller;
use core\base\BaseController;
use addon\tk_jhkd\app\service\core\TkjhkdNoticeService;
use think\Response;
class Notice extends BaseController
{
    /**
     * 登录
     * @return Response
     */
    public function list()
    {
        $data = $this->request->params([]);
        return success((new TkjhkdNoticeService())->getPage($data));
    }
}