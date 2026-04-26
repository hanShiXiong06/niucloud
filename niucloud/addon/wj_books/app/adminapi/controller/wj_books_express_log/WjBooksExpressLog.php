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

namespace addon\wj_books\app\adminapi\controller\wj_books_express_log;

use addon\wj_books\app\service\admin\wj_books_express_log\WjBooksExpressLogService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 物流回调日志控制器
 * Class WjBooksExpressLog
 * @package addon\wj_books\app\adminapi\controller\wj_books_express_log
 */
class WjBooksExpressLog extends BaseAdminController
{
    /**
     * 获取物流回调日志列表
     * @return Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["waybill", ""],
            ["shopbill", ""],
            ["order_id", ""],
            ["type_code", ""],
            ["start_time", ""],
            ["end_time", ""],
            ["page", 1],
            ["limit", 10]
        ]);
        
        return success((new WjBooksExpressLogService())->getList($data));
    }

    /**
     * 获取物流回调日志详情
     * @param int $id
     * @return Response
     */
    public function info(int $id)
    {
        return success((new WjBooksExpressLogService())->getInfo($id));
    }

    /**
     * 删除物流回调日志
     * @param int $id
     * @return Response
     */
    public function del(int $id)
    {
        (new WjBooksExpressLogService())->delete($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 清空物流回调日志
     * @return Response
     */
    public function clear()
    {
        (new WjBooksExpressLogService())->clear();
        return success('DELETE_SUCCESS');
    }
} 