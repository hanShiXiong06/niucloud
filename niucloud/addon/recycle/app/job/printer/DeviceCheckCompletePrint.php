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
namespace addon\recycle\app\job\printer;

use core\base\BaseJob;
use think\facade\Log;
use addon\recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use addon\recycle\app\service\admin\recycle_order\RecycleDeviceService;
use addon\recycle\app\model\printer\RecyclePrinterTemplate;
use addon\recycle\app\service\admin\printer\RecyclePrinterService;

/**
 * 质检完成后打印标签事件
 */
class DeviceCheckCompletePrint extends BaseJob
{
    public function doJob($data)
    {
        try {
            Log::record('【打印标签】开始处理设备质检完成后打印标签: ' . json_encode($data), 'info');
            
            // 检查数据
            if (empty($data) || !isset($data['device_id']) || !isset($data['site_id'])) {
                Log::record('【打印标签】缺少必要参数', 'error');
                return false;
            }
            
            $device_id = $data['device_id'];
            $site_id = $data['site_id'];
            $printer_id = $data['printer_id'] ?? 0;
            
            // 获取设备信息
            $deviceService = new RecycleDeviceService($site_id);
            $deviceInfo = $deviceService->getInfo($device_id);
            
            if (empty($deviceInfo)) {
                Log::record('【打印标签】设备不存在: ' . $device_id, 'error');
                return false;
            }
            
            // 如果没有指定打印机，尝试获取默认打印机
            if (empty($printer_id)) {
                // 获取默认标签打印机
                $printerService = new RecyclePrinterService($site_id);
                $printer = $printerService->getDefaultPrinter('label');
                
                if (!empty($printer)) {
                    $printer_id = $printer['printer_id'];
                    Log::record('【打印标签】使用默认标签打印机: ' . $printer_id, 'info');
                } else {
                    // 尝试获取任意默认打印机
                    $printer = $printerService->getDefaultPrinter();
                    if (!empty($printer)) {
                        $printer_id = $printer['printer_id'];
                        Log::record('【打印标签】没有找到标签打印机，使用默认打印机: ' . $printer_id, 'warning');
                    }
                }
            }
            
            if (empty($printer_id)) {
                Log::record('【打印标签】没有找到可用的打印机，但仍尝试使用默认模板打印', 'warning');
            }
            
            // 获取默认标签模板，可以传0表示使用默认模板
            $template_id = 0;
            
            // 准备打印数据，尽可能添加更多信息
            $printData = [
                'order_no' => $deviceInfo['order_no'] ?? '',
                'category_name' => $deviceInfo['category_name'] ?? '未知分类',
                'model' => $deviceInfo['model'] ?? '',
                'imei' => $deviceInfo['imei'] ?? '',
                'sn' => $deviceInfo['sn'] ?? '',
                'weight' => $deviceInfo['weight'] ?? '0',
                'check_result' => $deviceInfo['check_result'] ?? '质检通过',
                'final_price' => $deviceInfo['final_price'] ?? '0',
                'time' => date('Y-m-d H:i:s', time()),
                'qrcode' => ($deviceInfo['order_no'] ?? '') . '-' . $device_id,
                'brand' => $deviceInfo['brand'] ?? '',
                'color' => $deviceInfo['color'] ?? '',
                'memory' => $deviceInfo['memory'] ?? '',
                'capacity' => $deviceInfo['capacity'] ?? '',
                'problem' => $deviceInfo['problem'] ?? '',
                'device_id' => $device_id,
                'copies' => $data['copies'] ?? 1 // 打印份数
            ];
            
            // 执行打印
            $templateService = new RecyclePrinterTemplateService($site_id);
            $result = $templateService->printLabelWithTemplate($printer_id, $template_id, $printData);
            
            // 记录打印结果
            if (isset($result['code']) && $result['code'] === 0) {
                Log::record('【打印标签】打印成功: ' . json_encode($result), 'info');
                return true;
            } else {
                $message = isset($result['message']) ? $result['message'] : '未知错误';
                $simulated = isset($result['data']['simulated']) && $result['data']['simulated'] === true;
                
                if ($simulated) {
                    // 模拟打印也视为成功
                    Log::record('【打印标签】模拟打印成功: ' . $message, 'info');
                    return true;
                } else {
                    Log::record('【打印标签】打印失败: ' . $message . ', 详情: ' . json_encode($result), 'error');
                    // 虽然打印失败，但不影响后续流程
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::record('【打印标签】打印标签异常: ' . $e->getMessage() . ', 堆栈：' . $e->getTraceAsString(), 'error');
            // 即使异常也不影响主流程
            return true;
        }
    }
} 