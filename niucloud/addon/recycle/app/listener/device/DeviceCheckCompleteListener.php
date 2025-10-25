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
namespace addon\recycle\app\listener\device;

use addon\recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use think\facade\Log;

/**
 * 设备质检完成事件监听
 */
class DeviceCheckCompleteListener
{
    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle($params)
    {
        if (empty($params['site_id']) || empty($params['device_id'])) {
            Log::info('设备质检完成事件参数不完整', $params);
            return '';
        }
        
        try {
            // 记录日志
            Log::info("回收设备质检完成事件触发，设备ID：" . $params['device_id'], [
                'site_id' => $params['site_id'],
                'device_id' => $params['device_id']
            ]);
            
            // 初始化打印模板服务，需要手动设置site_id
            $template_service = new RecyclePrinterTemplateService();
            // 通过反射设置site_id属性
            $reflection = new \ReflectionClass($template_service);
            $site_id_property = $reflection->getProperty('site_id');
            $site_id_property->setAccessible(true);
            $site_id_property->setValue($template_service, $params['site_id']);
            
            // 检查是否有可用的默认模板
            $default_template = $template_service->getDefaultTemplate('device_label');
            if (empty($default_template)) {
                Log::info("未找到默认的设备标签模板，跳过自动打印", [
                    'device_id' => $params['device_id'],
                    'site_id' => $params['site_id']
                ]);
                return '';
            }
            
            // 检查是否有可用的打印机
            $default_printer = $template_service->getDefaultPrinter();
            if (empty($default_printer)) {
                Log::info("未找到可用的打印机，跳过自动打印", [
                    'device_id' => $params['device_id'],
                    'site_id' => $params['site_id']
                ]);
                return '';
            }
            
            // 执行自动打印
            $print_result = $template_service->printDeviceLabel($params['device_id'], 'device_label');
            
            if ($print_result['success']) {
                Log::info("设备质检完成，自动打印标签成功", [
                    'device_id' => $params['device_id'],
                    'site_id' => $params['site_id'],
                    'template_name' => $default_template['template_name'] ?? '',
                    'printer_name' => $default_printer['printer_name'] ?? '',
                    'print_result' => $print_result
                ]);
            } else {
                Log::warning("设备质检完成，自动打印标签失败", [
                    'device_id' => $params['device_id'],
                    'site_id' => $params['site_id'],
                    'error_message' => $print_result['message'] ?? '未知错误',
                    'print_result' => $print_result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("设备质检完成事件处理异常", [
                'device_id' => $params['device_id'],
                'site_id' => $params['site_id'],
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return '';
    }
} 