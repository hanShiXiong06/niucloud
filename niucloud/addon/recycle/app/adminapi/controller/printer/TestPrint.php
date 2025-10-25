<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\adminapi\controller\printer;

use addon\recycle\app\adminapi\controller\BaseAdminApiController;
use app\service\core\printer\CorePrinterService;
use think\facade\Event;
use think\facade\Queue;

/**
 * 打印测试
 * Class TestPrint
 * @package addon\recycle\app\adminapi\controller\printer
 */
class TestPrint extends BaseAdminApiController
{
    /**
     * 测试打印标签
     * @return mixed
     */
    public function deviceLabel()
    {
        // 接收参数
        $params = $this->request->param([
            ['device_id', 0],               // 设备ID，如果为0则使用模拟数据
            ['printer_id', 0],              // 打印机ID，0表示使用测试打印机
            ['trigger', 'manual']           // 触发类型，默认为手动
        ]);
        
        // 触发打印标签事件
        $data = [
            'site_id' => $this->site_id,
            'type' => 'recycleDevice',
            'trigger' => $params['trigger'],
            'business' => [
                'device_id' => $params['device_id']
            ]
        ];
        
        // 触发打印标签事件
        $event_result = Event::trigger('printer', $data, true);
        
        // 处理打印结果
        if (!empty($event_result) && isset($event_result['code'])) {
            if ($event_result['code'] == 0) {
                // 处理具体的打印任务
                $printerService = new CorePrinterService();
                if (!empty($event_result['data'])) {
                    $print_results = [];
                    foreach ($event_result['data'] as $item) {
                        // 执行打印
                        $print_result = $printerService->print($item['printer_info'], $item['origin_id'], $item['content']);
                        $print_results[] = $print_result;
                    }
                    return success('打印任务已发送', ['results' => $print_results]);
                } else {
                    return error('没有可用的打印数据');
                }
            } else {
                return error($event_result['message']);
            }
        } else {
            return error('未触发有效打印事件');
        }
    }
    
    /**
     * 测试打印订单小票
     * @return mixed
     */
    public function recycleOrder()
    {
        // 接收参数
        $params = $this->request->param([
            ['order_id', 0],                // 订单ID，如果为0则使用模拟数据
            ['printer_id', 0],              // 打印机ID，0表示使用测试打印机
            ['trigger', 'manual']           // 触发类型，默认为手动
        ]);
        
        // 触发打印小票事件
        $data = [
            'site_id' => $this->site_id,
            'type' => 'recycleOrder',
            'trigger' => $params['trigger'],
            'business' => [
                'order_id' => $params['order_id']
            ]
        ];
        
        // 触发打印小票事件
        $event_result = Event::trigger('printer', $data, true);
        
        // 处理打印结果
        if (!empty($event_result) && isset($event_result['code'])) {
            if ($event_result['code'] == 0) {
                // 处理具体的打印任务
                $printerService = new CorePrinterService();
                if (!empty($event_result['data'])) {
                    $print_results = [];
                    foreach ($event_result['data'] as $item) {
                        // 执行打印
                        $print_result = $printerService->print($item['printer_info'], $item['origin_id'], $item['content']);
                        $print_results[] = $print_result;
                    }
                    return success('打印任务已发送', ['results' => $print_results]);
                } else {
                    return error('没有可用的打印数据');
                }
            } else {
                return error($event_result['message']);
            }
        } else {
            return error('未触发有效打印事件');
        }
    }
} 