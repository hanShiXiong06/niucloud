<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use addon\recycle\app\service\admin\DeviceQueryApiService;
use addon\recycle\app\service\admin\DeviceQueryService;
use core\base\BaseAdminController;

/**
 * 设备查询API接口清单控制器
 * Class DeviceQueryApiController
 * @package addon\recycle\app\adminapi\controller
 */
class DeviceQueryApiController extends BaseAdminController
{
    /**
     * 获取设备查询API接口清单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['name', ''],
            ['version', ''],
            ['status', ''],
            ['create_at', ['', '']],
        ]);
        
        return success((new DeviceQueryApiService())->getPage($data));
    }

    /**
     * 设备查询API接口清单详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new DeviceQueryApiService())->getInfo($id));
    }

    /**
     * 添加设备查询API接口清单
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['name', ''],
            ['version', '1.0.0'],
            ['api_list', []],
            ['status', 1],
            ['remark', ''],
        ]);

        $this->validate($data, 'addon\recycle\app\validate\DeviceQueryApiValidate.add');
        $id = (new DeviceQueryApiService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑设备查询API接口清单
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['name', ''],
            ['version', ''],
            ['api_list', []],
            ['status', 1],
            ['remark', ''],
        ]);

        $this->validate($data, 'addon\recycle\app\validate\DeviceQueryApiValidate.edit');
        (new DeviceQueryApiService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除设备查询API接口清单
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new DeviceQueryApiService())->del($id);
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
        (new DeviceQueryApiService())->modifyStatus($id, $data['status']);
        return success('MODIFY_SUCCESS');
    }

    /**
     * 获取默认API清单
     * @return \think\Response
     */
    public function getDefaultApiList()
    {
        $result = (new DeviceQueryApiService())->getDefaultApiList();
        return success($result);
    }

    /**
     * 初始化默认API清单
     * @return \think\Response
     */
    public function initDefaultApiList()
    {
        $result = (new DeviceQueryApiService())->initDefaultApiList();
        return success( $result);
    }

    /**
     * 根据端点获取API信息
     * @return \think\Response
     */
    public function getApiByEndpoint()
    {
        $data = $this->request->params([
            ['endpoint', ''],
        ]);
        
        $result = (new DeviceQueryApiService())->getApiByEndpoint($data['endpoint']);
        return success('GET_SUCCESS', $result);
    }

    /**
     * 获取分类下的API列表
     * @return \think\Response
     */
    public function getApisByCategory()
    {
        $data = $this->request->params([
            ['category', ''],
        ]);
        
        $result = (new DeviceQueryApiService())->getApisByCategory($data['category']);
        return success('GET_SUCCESS', $result);
    }

    // 获取设备的基本信息 coverage
    public function getCoverage()
    {
        $data = $this->request->params([
            ['imei', ''],
            ['brand', ''],

        ]);
        $result = (new DeviceQueryApiService())->getCoverage($data);
        return  success($result);
    }

    // 获取设备的激活锁 activationlock
    public function getActivationlock()
    {
        $data = $this->request->params([
            ['imei', ''],
        ]);
        $result = (new DeviceQueryApiService())->getActivationlock($data['imei']);
        return success($result);
    }

    // 获取设备的mdm 监管锁 mdm
    public function getMdm()
    {
        $data = $this->request->params([
            ['imei', ''],
        ]);
        $result = (new DeviceQueryApiService())->getMdm($data['imei']);
        return success($result);
    }
    // 查询 快递的物流信息
      public function getExpress()
      {
        $data = $this->request->params([
          ['express_code', ''],
          ['mobile', ''],
        ]);
        $result = (new DeviceQueryService())->getExpress($data['express_code'], $data['mobile']);
        return success($result);
      }
    
} 