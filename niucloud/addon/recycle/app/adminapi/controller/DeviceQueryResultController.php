<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use addon\recycle\app\service\admin\DeviceQueryResultService;
use core\base\BaseAdminController;

/**
 * 设备查询结果控制器
 * Class DeviceQueryResultController
 * @package addon\recycle\app\adminapi\controller
 */
class DeviceQueryResultController extends BaseAdminController
{
    /**
     * 获取设备查询结果列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['query_code', ''],
            ['api_endpoint', ''],
            ['query_type', ''],
            ['status', ''],
            ['create_at', ['', '']],
        ]);
        
        return success((new DeviceQueryResultService())->getPage($data));
    }

    /**
     * 设备查询结果详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new DeviceQueryResultService())->getInfo($id));
    }

    /**
     * 删除设备查询结果
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new DeviceQueryResultService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 批量删除设备查询结果
     * @return \think\Response
     */
    public function batchDel()
    {
        $data = $this->request->params([
            ['ids', []],
        ]);
        
        (new DeviceQueryResultService())->batchDel($data['ids']);
        return success('DELETE_SUCCESS');
    }

    /**
     * 获取查询统计
     * @return \think\Response
     */
    public function getStats()
    {
        $data = $this->request->params([
            ['start_date', ''],
            ['end_date', ''],
            ['query_type', ''],
            ['api_endpoint', ''],
        ]);
        
        $result = (new DeviceQueryResultService())->getStats($data);
        return success('GET_SUCCESS', $result);
    }

    /**
     * 清理过期缓存
     * @return \think\Response
     */
    public function cleanCache()
    {
        $data = $this->request->params([
            ['expire_time', 604800], // 默认7天
        ]);
        
        $result = (new DeviceQueryResultService())->cleanCache($data['expire_time']);
        return success('CLEAN_SUCCESS', ['cleaned_count' => $result]);
    }

    /**
     * 重新查询
     * @param int $id
     * @return \think\Response
     */
    public function requery(int $id)
    {
        $result = (new DeviceQueryResultService())->requery($id);
        return success('REQUERY_SUCCESS', $result);
    }

    /**
     * 导出查询结果
     * @return \think\Response
     */
    public function export()
    {
        $data = $this->request->params([
            ['query_code', ''],
            ['api_endpoint', ''],
            ['query_type', ''],
            ['status', ''],
            ['create_at', ['', '']],
        ]);
        
        $result = (new DeviceQueryResultService())->export($data);
        return success('EXPORT_SUCCESS', $result);
    }

    // 查询当前站点的总消费
    public function getTotalConsumption(){
        $data = $this->request->params([
            ['create_at', ['', '']],
        ]);
        $result = (new DeviceQueryResultService())->getTotalConsumption($data);
        return success( $result);
    }
} 