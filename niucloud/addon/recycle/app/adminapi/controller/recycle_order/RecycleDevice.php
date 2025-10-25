<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\recycle_order;

use addon\recycle\app\service\admin\recycle_order\RecycleDeviceService;
use addon\recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use addon\recycle\app\validate\RecycleDeviceValidate;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\App;

/**
 * 回收设备管理控制器
 * Class RecycleDevice
 * @package addon\recycle\app\adminapi\controller\recycle_order
 */
class RecycleDevice extends BaseAdminController
{
    /**
     * @var RecycleDeviceService
     */
    protected $service;

    /**
     * @var RecycleDeviceValidate
     */
    protected $validate;

    /**
     * @var RecyclePrinterTemplateService
     */
    protected $template_service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleDeviceService();
        $this->validate = new RecycleDeviceValidate();
        $this->template_service = new RecyclePrinterTemplateService();
    }

    /**
     * 获取设备列表
     * @return mixed
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['device_name', ''],
            ['imei', ''],
            ['model', ''],
            ['status', ''],
            ['create_at', []],
            ['page', 1],
            ['limit', 10]
        ]);

        // 参数验证
        $this->validate->scene('list')->check($data);

        return success($this->service->getPage($data));
    }

    /**
     * 获取设备信息
     * @param int $id
     * @return mixed
     */
    public function getInfo(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        $data = $this->service->getInfo($id);
        
        // 获取设备日志
        $logModel = new \addon\recycle\app\model\RecycleDeviceLog();
        $logs = $logModel->getDeviceLogList(['device_id' => $id], 1, 20, 'id desc');
        $data['logs'] = $logs['list'];
        
        return success($data);
    }

    /**
     * 添加设备
     * @return mixed
     */
    public function add()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['imei', ''],
            ['model', ''],
            ['initial_price', 0],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('create')->check($data);

        return success($this->service->add($data));
    }

    /**
     * 更新设备信息
     * @param int $id
     * @return mixed
     */
    public function update(int $id)
    {
        $data = $this->request->params([
            ['data', []]
        ]);

        // 参数验证
        $this->validate->scene('update')->check(array_merge(['id' => $id], $data['data']));

        // 检查是否是质检操作
        if (isset($data['data']['action']) && $data['data']['action'] == 'check') {
            // 组装质检数据
            $checkData = [
                'check_result' => $data['data']['check_result'] ?? '',
                'check_images' => $data['data']['check_images'] ?? '',
            ];
            
            // 如果有最终价格，添加到质检数据中
            if (isset($data['data']['final_price']) && $data['data']['final_price'] > 0) {
                $checkData['final_price'] = $data['data']['final_price'];
            }
            
            // 如果有check_status，添加到质检数据中
            if (isset($data['data']['check_status'])) {
                $checkData['check_status'] = $data['data']['check_status'];
            }
            
            // imei
            if (isset($data['data']['imei'])) {
                $checkData['imei'] = $data['data']['imei'];
            }
            // info
            if (isset($data['data']['info'])) {
                $checkData['info'] = $data['data']['info'];
            }
            // model
            if (isset($data['data']['model'])) {
                $checkData['model'] = $data['data']['model'];
            }
            

            // 调用质检完成方法
            return success($this->service->completeCheck($id, $checkData, $data['data']['remark'] ?? ''));
        }
        
        return success($this->service->update($id, $data['data']));
    }

    /**
     * 删除设备
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        // 参数验证
        $this->validate->scene('delete')->check(['id' => $id]);

        return success($this->service->delete($id));
    }

    /**
     * 开始质检设备
     * @param int $id
     * @return mixed
     */
    public function startCheck(int $id)
    {
        $data = $this->request->params([
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('check')->check(array_merge(['id' => $id], $data));

        return success($this->service->startCheck($id, $data['remark']));
    }

    /**
     * 完成质检设备
     * @param int $id
     * @return mixed
     */
    public function completeCheck(int $id)
    {
        $data = $this->request->params([
            ['check_data', []],
            ['final_price', 0],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('check')->check(array_merge(['id' => $id], $data));

        // 组装质检数据
        $checkData = $data['check_data'];
        if (isset($data['final_price']) && $data['final_price'] > 0) {
            $checkData['final_price'] = $data['final_price'];
        }

        return success($this->service->completeCheck($id, $checkData, $data['remark']));
    }

    /**
     * 确认设备价格
     * @param int $id
     * @return mixed
     */
    public function confirmPrice(int $id)
    {
        $data = $this->request->params([
            ['final_price', ''],
            ['remark', '']
        ]);
      
        // 参数验证
        $this->validate->scene('price')->check(array_merge(['id' => $id], $data));

        return success($this->service->confirmPrice($id, $data['final_price'], $data['remark']));
    }

    /**
     * 回收设备
     * @param int $id
     * @return mixed
     */
    public function recycle(int $id)
    {
        $data = $this->request->params([
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('recycle')->check(array_merge(['id' => $id], $data));

        return success($this->service->recycle($id, $data['remark']));
    }

    /**
     * 退回设备
     * @param int $id
     * @return mixed
     */
    public function returnDevice(int $id)
    {
        $data = $this->request->params([
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('return')->check(array_merge(['id' => $id], $data));

        return success($this->service->returnDevice($id, $data['remark']));
    }

    /**
     * 批量更新设备状态
     * @return mixed
     */
    public function batchUpdateStatus()
    {
        $data = $this->request->params([
            ['ids', ''],
            ['status', 0],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('batch')->check($data);

        // 将逗号分隔的ID转为数组
        $ids = is_string($data['ids']) ? explode(',', $data['ids']) : $data['ids'];
        if (empty($ids)) {
            return error('请选择设备');
        }

        return success($this->service->batchUpdateStatus($ids, (int)$data['status'], $data['remark']));
    }

    /**
     * 批量回收设备
     * @return mixed
     */
    public function batchRecycle()
    {
        $data = $this->request->params([
            ['ids', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('batch')->check($data);

        // 将逗号分隔的ID转为数组
        $ids = is_string($data['ids']) ? explode(',', $data['ids']) : $data['ids'];
        if (empty($ids)) {
            return error('请选择设备');
        }

        return success($this->service->batchRecycle($ids, $data['remark']));
    }

    /**
     * 批量退回设备
     * @return mixed
     */
    public function batchReturn()
    {
        $data = $this->request->params([
            ['ids', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('batch')->check($data);

        // 将逗号分隔的ID转为数组
        $ids = is_string($data['ids']) ? explode(',', $data['ids']) : $data['ids'];
        if (empty($ids)) {
            return error('请选择设备');
        }

        return success($this->service->batchReturn($ids, $data['remark']));
    }

    /**
     * 获取IMEI信息
     * @param string $imei
     * @return mixed
     */
    public function getImeiInfo(string $imei)
    {
        // 参数验证
        $this->validate->scene('imei')->check(['imei' => $imei]);

        return success($this->service->getImeiInfo($imei));
    }

    /**
     * 打印设备标签
     * @param int $id
     * @return mixed
     */
    public function printDeviceLabel(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        $data = $this->request->params([
            ['template_type', 'device_label']
        ]);
        
        $result = $this->template_service->printDeviceLabel($id, $data['template_type']);
        
      

        if ($result['success']) {
            return success($result);
        } 
    }

    /**
     * 获取设备打印数据
     * @param int $id 设备ID
     * @return mixed
     */
    public function getPrintData(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);
        
        try {
            $data = $this->template_service->getDevicePrintData($id);
            
            return success([
                'device_data' => $data,
                'available_variables' => array_keys($data)
            ]);
            
        } catch (\Exception $e) {
            return error('获取设备数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试设备标签打印
     * @param int $id 设备ID
     * @return mixed
     */
    public function testPrint(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);
        
        $data = $this->request->params([
            ['template_id', 0],
            ['template_type', 'device_label']
        ]);
        
        try {
            // 获取设备数据
            $device_data = $this->template_service->getDevicePrintData($id);
            
            // 确定使用的模板
            if (!empty($data['template_id'])) {
                // 使用指定模板进行测试打印
                $result = $this->template_service->testPrint($data['template_id'], $device_data);
            } else {
                // 使用默认模板打印
                $result = $this->template_service->printDeviceLabel($id, $data['template_type']);
            }
            
            return success([
                'device_id' => $id,
                'device_data' => $device_data,
                'print_result' => $result
            ]);
            
        } catch (\Exception $e) {
            return error('测试打印失败: ' . $e->getMessage());
        }
    }
}
