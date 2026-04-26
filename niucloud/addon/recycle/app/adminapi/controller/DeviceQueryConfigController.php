<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use addon\recycle\app\service\admin\DeviceQueryConfigService;
use core\base\BaseAdminController;

/**
 * 设备查询配置控制器
 * Class DeviceQueryConfigController
 * @package addon\recycle\app\adminapi\controller
 */
class DeviceQueryConfigController extends BaseAdminController
{
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
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
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
            ['name', ''],
            ['api_key', ''],
            ['base_url', 'http://api.3023data.com'],
            ['enabled_apis', []],
            ['timeout', 30],
            ['max_retry', 3],
            ['cache_time', 3600],
            ['daily_limit', 1000],
            ['balance', 0.00],
            ['status', 1],
            ['remark', ''],
        ]);

        // $this->validate($data, 'addon\recycle\app\validate\DeviceQueryConfigValidate.add');
        $id = (new DeviceQueryConfigService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑设备查询配置
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['name', ''],
            ['api_key', ''],
            ['base_url', ''],
            ['enabled_apis', []],
            ['timeout', 30],
            ['max_retry', 3],
            ['cache_time', 3600],
            ['daily_limit', 1000],
            ['balance', 0.00],
            ['status', 1],
            ['remark', ''],
        ]);

        // $this->validate($data, 'addon\recycle\app\validate\DeviceQueryConfigValidate.edit');
        (new DeviceQueryConfigService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除设备查询配置
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new DeviceQueryConfigService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 修改状态
     * @param int $id
     * @return \think\Response
     */
    public function modifyStatus(int $id)
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
    public function testConnection(int $id)
    {
        $result = (new DeviceQueryConfigService())->testConnection($id);
        return success('TEST_SUCCESS', $result);
    }

    /**
     * 获取查询统计
     * @param int $id
     * @return \think\Response
     */
    public function getStats(int $id)
    {
        $data = $this->request->params([
            ['start_date', ''],
            ['end_date', ''],
        ]);
        
        $result = (new DeviceQueryConfigService())->getStats($id, $data);
        return success('GET_SUCCESS', $result);
    }
} 