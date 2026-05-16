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

namespace addon\recycle\app\adminapi\controller\price_image;

use addon\recycle\app\service\admin\price_image\PriceImageService;
use addon\recycle\app\service\admin\recycle_excel\RecycleExcelService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 报价单图片生成控制器
 * Class PriceImageController
 * @package addon\recycle\app\adminapi\controller\price_image
 */
class PriceImageController extends BaseAdminController
{
    
    /**
     * 检查环境支持
     * @return Response
     */
    public function checkEnvironment()
    {
        try {
            $service = new PriceImageService();
            $hasImagick = $service->checkImagickExtension();
            $fontAvailable = $hasImagick ? $service->checkFontAvailable() : false;
            
            return success('环境检查完成', [
                'imagick_installed' => $hasImagick,
                'imagick_version' => $hasImagick ? Imagick::getVersion()['versionString'] : null,
                'font_available' => $fontAvailable,
                'font_status' => $fontAvailable ? '字体渲染正常' : '字体渲染不可用，将使用纯图形模式',
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'temp_dir_writable' => is_writable(runtime_path()),
                'temp_dir_path' => runtime_path() . 'temp'
            ]);
            
        } catch (\Exception $e) {
            return fail('环境检查失败：' . $e->getMessage());
        }
    }
    
    /**
     * 生成报价单图片
     * @return Response
     */
    public function generate()
    {
        $data = $this->request->params([
            ['brand_id', 158],
            ['device_type', 'phone'],
            ['create_at', ''],
            ['keyword', '']
        ]);
        
        $service = new RecycleExcelService();
        $params = $service->getPage($data);
        
      
        try {

          
            
            $service = new PriceImageService();
            $result = $service->generateImage($params);
            
            return success($result['message'], $result);
            
        } catch (\Exception $e) {
            return fail('生成失败：' . $e->getMessage());
        }
    }
    
    /**
     * 批量生成报价单图片
     * @return Response
     */
    public function batchGenerate()
    {
        try {
            $data = $this->request->param();
            
            // 验证批量参数
            $batchParams = $data['batch_params'] ?? [];
            if (empty($batchParams) || !is_array($batchParams)) {
                return fail('批量参数不能为空');
            }
            
            $service = new PriceImageService();
            $results = [];
            $success_count = 0;
            $error_count = 0;
            
            foreach ($batchParams as $index => $params) {
                try {
                    $result = $service->generateImage($params);
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result['data']
                    ];
                    $success_count++;
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $error_count++;
                }
            }
            
            return success('批量生成完成', [
                'total' => count($batchParams),
                'success_count' => $success_count,
                'error_count' => $error_count,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            return fail('批量生成失败：' . $e->getMessage());
        }
    }
    
    /**
     * 预览图片（返回base64）
     * @return Response
     */
    public function preview()
    {
        try {
            $params = $this->request->param();
            
            $service = new PriceImageService();
            $result = $service->generateImage($params);
            
            // 读取图片并转换为base64
            $filepath = $result['data']['filepath'];
            if (!file_exists($filepath)) {
                return fail('图片文件不存在');
            }
            
            $imageData = file_get_contents($filepath);
            $base64 = 'data:image/png;base64,' . base64_encode($imageData);
            
            // 删除临时文件
            unlink($filepath);
            
            return success('预览生成成功', [
                'base64' => $base64,
                'width' => $result['data']['width'],
                'height' => $result['data']['height'],
                'device_count' => $result['data']['device_count']
            ]);
            
        } catch (\Exception $e) {
            return fail('预览生成失败：' . $e->getMessage());
        }
    }
    
    /**
     * 下载图片
     * @return Response
     */
    public function download()
    {
        try {
            $filename = $this->request->param('filename');
            if (empty($filename)) {
                return fail('文件名不能为空');
            }
            
            $filepath = runtime_path() . 'temp' . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($filepath)) {
                return fail('文件不存在或已过期');
            }
            
            // 设置下载头
            $response = Response::create()->header([
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => filesize($filepath)
            ]);
            
            // 输出文件内容
            $response->content(file_get_contents($filepath));
            
            // 删除临时文件
            unlink($filepath);
            
            return $response;
            
        } catch (\Exception $e) {
            return fail('下载失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取图片生成历史记录
     * @return Response
     */
    public function getHistory()
    {
        try {
            // 这里可以实现历史记录功能
            // 暂时返回空数据
            return success('获取成功', [
                'list' => [],
                'total' => 0
            ]);
            
        } catch (\Exception $e) {
            return fail('获取历史记录失败：' . $e->getMessage());
        }
    }
    
    /**
     * 清理临时文件
     * @return Response
     */
    public function cleanTemp()
    {
        try {
            $tempDir = runtime_path() . 'temp';
            if (!is_dir($tempDir)) {
                return success('临时目录不存在');
            }
            
            $files = glob($tempDir . DIRECTORY_SEPARATOR . 'price_sheet_*.png');
            $cleaned = 0;
            
            foreach ($files as $file) {
                // 只删除1小时前的文件
                if (filemtime($file) < time() - 3600) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
            
            return success('清理完成', [
                'cleaned_count' => $cleaned
            ]);
            
        } catch (\Exception $e) {
            return fail('清理失败：' . $e->getMessage());
        }
    }
    
    /**
     * 调试数据源（开发阶段使用）
     * @return Response
     */
    public function debugData()
    {
        try {
            $params = $this->request->param();
            
            $service = new PriceImageService();
            $excelService = new \addon\recycle\app\service\admin\recycle_excel\RecycleExcelService();
            
            $deviceList = $excelService->getPage($params);
            
            return success('数据调试完成', [
                'count' => count($deviceList),
                'sample_data' => array_slice($deviceList, 0, 3), // 只返回前3条作为样本
                'data_structure' => !empty($deviceList) ? array_keys($deviceList[0]) : [],
                'params' => $params
            ]);
            
        } catch (\Exception $e) {
            return fail('数据调试失败：' . $e->getMessage());
        }
    }
    
    /**
     * 测试数据映射（开发阶段使用）
     * @return Response
     */
    public function testMapping()
    {
        try {
            $params = $this->request->param();
            
            $service = new PriceImageService();
            $result = $service->testDataRetrieval($params);
            
            if ($result['success']) {
                return success($result['message'], $result['data']);
            } else {
                return fail($result['message']);
            }
            
        } catch (\Exception $e) {
            return fail('数据映射测试失败：' . $e->getMessage());
        }
    }
} 