<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\device_query;

use addon\hsx_recycle\app\service\admin\device_query\DeviceQueryConfigService;
use core\base\BaseAdminController;

/**
 * 设备查询配置控制器
 * Class DeviceQueryConfigController
 * @package addon\hsx_recycle\app\adminapi\controller
 */
class DeviceQueryConfigController extends BaseAdminController
{
    /**
     * 获取设备查询完整配置
     */
    public function getConfig()
    {
        return success((new DeviceQueryConfigService())->getPage());
    }

    /**
     * 保存设备查询完整配置
     */
    public function setConfig()
    {
        $data = $this->request->post();
        (new DeviceQueryConfigService())->saveConfigCenter($data);
        return success('SAVE_SUCCESS');
    }

    /**
     * 获取设备查询配置列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['name', ''],
            ['status', ''],
            ['create_at', ['', '']],
        ]);
        
        return success((new DeviceQueryConfigService())->getPage($data));
    }

    /**
     * 设备查询配置详情
     * @return \think\Response
     */
    public function info(string $id)
    {
        return success((new DeviceQueryConfigService())->getInfo($id));
    }

    /**
     * 添加设备查询配置
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['code', ''],
            ['name', ''],
            ['category', 'other'],
            ['query_type', 'other'],
            ['cost_price', 0],
            ['cache_ttl', 0],
            ['sort', 0],
            ['enabled', 1],
            ['show_in_check', 0],
            ['result_handler', 'generic'],
        ]);
        $id = (new DeviceQueryConfigService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑设备查询配置
     * @return \think\Response
     */
    public function edit(string $id)
    {
        $data = $this->request->params([
            ['code', ''],
            ['name', ''],
            ['category', 'other'],
            ['query_type', 'other'],
            ['cost_price', 0],
            ['cache_ttl', 0],
            ['sort', 0],
            ['enabled', 1],
            ['show_in_check', 0],
            ['result_handler', 'generic'],
        ]);
        (new DeviceQueryConfigService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除设备查询配置
     * @param int $id
     * @return \think\Response
     */
    public function del(string $id)
    {
        (new DeviceQueryConfigService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 修改状态
     * @param int $id
     * @return \think\Response
     */
    public function modifyStatus(string $id)
    {
        $data = $this->request->params([
            ['status', 1],
        ]);
        (new DeviceQueryConfigService())->modifyStatus($id, $data['status']);
        return success('MODIFY_SUCCESS');
    }

    /**
     * 测试API连接
     * @param int $id
     * @return \think\Response
     */
    public function testConnection(string $id)
    {
        $data = $this->request->params([
            ['query_code', ''],
            ['query_type', ''],
            ['channel_key', ''],
        ]);
        $result = (new DeviceQueryConfigService())->testConnection($id, $data);
        return success('TEST_SUCCESS', $result);
    }

    /**
     * 获取查询统计
     * @param int $id
     * @return \think\Response
     */
    public function getStats(string $id)
    {
        $data = $this->request->params([
            ['start_date', ''],
            ['end_date', ''],
        ]);
        
        $result = (new DeviceQueryConfigService())->getStats($id, $data);
        return success('GET_SUCCESS', $result);
    }

    /**
     * 查询渠道余额
     */
    public function balance(string $channelKey)
    {
        $result = (new DeviceQueryConfigService())->getChannelBalance($channelKey);
        return success('GET_SUCCESS', $result);
    }
} 
