<?php
namespace addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_list;

use addon\hsx_phone_query\app\service\admin\hsx_phone_query_list\HsxPhoneQueryListService;
use core\base\BaseAdminController;

/**
 * 查询记录管理控制器
 */
class HsxPhoneQueryList extends BaseAdminController
{
    /**
     * 获取查询记录列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['keyword', ''],
            ['start_time', ''],
            ['end_time', ''],
            ['service_code', ''],
            ['provider_key', ''],
            ['channel_key', ''],
            ['status', '']
        ]);
        $res = (new HsxPhoneQueryListService())->getList($data);
        return success($res);
    }

    /**
     * 获取查询运营看板
     */
    public function dashboard()
    {
        $data = $this->request->params([
            ['start_time', ''],
            ['end_time', '']
        ]);
        return success((new HsxPhoneQueryListService())->getDashboard($data));
    }

    /**
     * 获取查询记录详情
     */
    public function info()
    {
        $id = $this->request->param('id');
        $res = (new HsxPhoneQueryListService())->getInfo($id);
        return success($res);
    }
}
