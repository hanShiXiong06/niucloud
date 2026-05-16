<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin;

use addon\recycle\app\model\DeviceQueryConfig;
use addon\recycle\app\service\admin\DeviceQueryService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 设备查询配置服务类
 * Class DeviceQueryConfigService
 * @package addon\recycle\app\service\admin
 */
class DeviceQueryConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceQueryConfig();
    }

    /**
     * 获取设备查询配置分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,name,api_key,base_url,timeout,cache_time,daily_limit,balance,status,create_at,update_at';
        $order = 'create_at desc';

        $search_model = $this->model
            ->where('site_id', $this->site_id)
            ->field($field)
            ->order($order)
            ->append(['status_name']);

        return $this->pageQuery($search_model);
    }

    /**
     * 获取设备查询配置信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,name,api_key,base_url,enabled_apis,timeout,max_retry,cache_time,daily_limit,balance,status,remark,create_at,update_at';

        $info = $this->model->field($field)->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('DEVICE_QUERY_CONFIG_NOT_EXIST');
        }
        return $info;
    }

    /**
     * 添加设备查询配置
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
       
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 设备查询配置编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除设备查询配置
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($model)) {
            throw new CommonException('DEVICE_QUERY_CONFIG_NOT_EXIST');
        }
        
        $res = $model->delete();
        return $res;
    }

    /**
     * 修改设备查询配置状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function modifyStatus(int $id, int $status)
    {
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update(['status' => $status]);
        return true;
    }

    /**
     * 测试API连接
     * @param int $id
     * @return array
     */
    public function testConnection(int $id)
    {
        $config = $this->getInfo($id);
        
        try {
            $queryService = new DeviceQueryService();
            // 使用一个测试IMEI进行连接测试
            $testImei = '123456789012345';
            $result = $queryService->queryDevice($testImei, '/apple/model', $this->site_id, false);
            
            return [
                'success' => true,
                'message' => 'API连接测试成功',
                'response_time' => $result['response_time'] ?? 0,
                'test_result' => $result
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API连接测试失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取查询统计
     * @param int $id
     * @param array $data
     * @return array
     */
    public function getStats(int $id, array $data = [])
    {
        $queryResultService = new DeviceQueryResultService();
        $statsData = array_merge($data, ['site_id' => $this->site_id]);
        return $queryResultService->getStats($statsData);
    }
} 