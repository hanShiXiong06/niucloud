<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin;

use addon\recycle\app\model\DeviceQueryApi;
use core\base\BaseAdminService;
use core\exception\CommonException;
use addon\recycle\app\service\admin\DeviceQueryService;
/**
 * 设备查询API接口清单服务类
 * Class DeviceQueryApiService
 * @package addon\recycle\app\service\admin
 */
class DeviceQueryApiService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceQueryApi();
    }

    /**
     * 获取设备查询API接口清单分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,name,version,status,api_list,remark,create_at,update_at';
        $order = 'create_at desc';

        $search_model = $this->model
            ->withSearch(['name', 'version', 'status', 'create_at'], $where)
            ->field($field)
            ->order($order)
            ->append(['status_name']);

        return $this->pageQuery($search_model);
    }

    /**
     * 获取设备查询API接口清单信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,name,version,api_list,status,remark,create_at,update_at';

        $info = $this->model->field($field)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('DEVICE_QUERY_API_NOT_EXIST');
        }
        return $info;
    }

    /**
     * 添加设备查询API接口清单
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 设备查询API接口清单编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $this->model->where('id', $id)->update($data);
        return true;
    }

    /**
     * 删除设备查询API接口清单
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where('id', $id)->find();
        if (empty($model)) {
            throw new CommonException('DEVICE_QUERY_API_NOT_EXIST');
        }
        
        $res = $model->delete();
        return $res;
    }

    /**
     * 修改设备查询API接口清单状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function modifyStatus(int $id, int $status)
    {
        $this->model->where('id', $id)->update(['status' => $status]);
        return true;
    }

    /**
     * 获取默认API清单
     * @return array
     */
    public function getDefaultApiList()
    {
        return DeviceQueryApi::getDefaultApiList();
    }

    /**
     * 初始化默认API清单
     * @return bool
     */
    public function initDefaultApiList()
    {
        return DeviceQueryApi::initDefaultApiList();
    }

    /**
     * 根据端点获取API信息
     * @param string $endpoint
     * @return array|null
     */
    public function getApiByEndpoint(string $endpoint)
    {
        return DeviceQueryApi::getApiByEndpoint($endpoint);
    }

    /**
     * 获取分类下的API列表
     * @param string $category
     * @return array
     */
    public function getApisByCategory(string $category = '')
    {
        $apiList = DeviceQueryApi::getApiList();
        
        if (empty($category)) {
            return $apiList;
        }
        
        return $apiList[$category] ?? [];
    }

    /**
     * 获取所有API分类
     * @return array
     */
    public function getApiCategories()
    {
        $apiList = DeviceQueryApi::getApiList();
        $categories = [];
        
        foreach ($apiList as $category => $apis) {
            $categoryName = '';
            switch ($category) {
                case 'apple':
                    $categoryName = '苹果设备';
                    break;
                case 'android':
                    $categoryName = '安卓设备';
                    break;
                case 'imei':
                    $categoryName = 'IMEI查询';
                    break;
                case 'other':
                    $categoryName = '其他查询';
                    break;
                default:
                    $categoryName = $category;
            }
            
            $categories[] = [
                'key' => $category,
                'name' => $categoryName,
                'count' => count($apis)
            ];
        }
        
        return $categories;
    }

    // 获取设备的基本信息 coverage
    public function getCoverage(array $data)
    {
        
        return (new DeviceQueryService())->getCoverage($data);
    }

    // 获取设备的激活锁 activationlock
    public function getActivationlock(string $imei = ''){
        return (new DeviceQueryService())->getActivationlock($imei);
    }

    //获取设备的mdm 监管锁 mdm
    public function getMdm(string $imei = ''){
        return (new DeviceQueryService())->getMdm($imei);
    }

    

    
} 