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

namespace addon\recycle\app\adminapi\controller\recycle_category;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\recycle_excel\RecycleExcelService;
use addon\recycle\app\service\admin\recycle_excel\RecycleExcelImportService;
use think\Response;
use think\facade\Request;

/**
 * Excel数据管理控制器
 * Class RecycleExcelController
 * @package addon\recycle\app\adminapi\controller\recycle_category
 */
class RecycleExcelController extends BaseAdminController
{
    /**
     * 获取设备列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['page', 1],
            ['limit', 20],
            ['brand_id', 158],
            ['device_type', 'phone'],
            ['create_at', ''],
            ['keyword', '']
        ]);
        
        return success('SUCCESS', (new RecycleExcelService())->getPage($data));
    }

    /**
     * 获取品牌列表
     * @return \think\Response
     */
    public function getBrandList()
    {
        return success('SUCCESS', (new RecycleExcelService())->getBrands());
    }

    /**
     * 获取统计信息
     * @return \think\Response
     */
    public function getStatistics()
    {
        return success('SUCCESS', (new RecycleExcelService())->getStatistics());
    }

    /**
     * 获取导入记录
     * @return \think\Response
     */
    public function records()
    {
        $data = $this->request->params([
            ["page", 1],
            ["limit", 10]
        ]);
        return success((new RecycleExcelService())->getRecords($data));
    }

    /**
     * 设备详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new RecycleExcelService())->getInfo($id));
    }

    /**
     * 搜索设备
     * @return \think\Response
     */
    public function search()
    {
        $data = $this->request->params([
            ["keyword", ""],
            ["limit", 10]
        ]);
        return success((new RecycleExcelService())->search($data));
    }

    /**
     * 批量删除设备
     * @return \think\Response
     */
    public function batchDelete()
    {
        $data = $this->request->params([
            ['ids', []]
        ]);
        
        return success('SUCCESS', (new RecycleExcelService())->batchDelete($data['ids']));
    }

    /**
     * 更新设备价格
     * @return \think\Response
     */
    public function updatePrice()
    {
        $data = $this->request->params([
            ['device_id', 0],
            ['price_data', []]
        ]);
        
        return success('SUCCESS', (new RecycleExcelService())->updatePrice($data));
    }

    /**
     * 导出设备数据
     * @return \think\Response
     */
    public function export()
    {
        $data = $this->request->params([
            ["brand_id", ""],
            ["device_type", ""],
            ["keyword", ""]
        ]);
        return success((new RecycleExcelService())->export($data));
    }

    /**
     * 获取价格图表数据
     * @return \think\Response
     */
    public function chart()
    {
        $data = $this->request->params([
            ["brand_id", ""],
            ["device_type", ""]
        ]);
        return success((new RecycleExcelService())->getChart($data));
    }

    public function deviceList()
    {
        $data = $this->request->params([
            ["brand_id", ""],
            ["device_type", ""],
            ["keyword", ""],
            ["page", 1],
            ["limit", 20]
        ]);
        return success((new RecycleExcelService())->getDeviceList($data));
    }

    /**
     * 上传Excel文件
     */
    public function uploadExcel()
    {
        try {
            $file = request()->file('file');
            if (empty($file)) {
                return fail('请选择要上传的Excel文件');
            }

            // 验证文件类型
            $allowedTypes = ['xlsx', 'xls'];
            $extension = $file->getOriginalExtension();
            if (!in_array(strtolower($extension), $allowedTypes)) {
                return fail('只支持上传 .xlsx 或 .xls 格式的Excel文件');
            }

            // 验证文件大小 (最大10MB)
            if ($file->getSize() > 10 * 1024 * 1024) {
                return fail('文件大小不能超过10MB');
            }

            $service = new RecycleExcelImportService();
            $result = $service->uploadFile($file);

            // 返回标准的文件上传格式，包含url字段
            return success('文件上传成功', [
                'url' => $result['file_path'],
                'file_path' => $result['file_path'],
                'file_name' => $result['file_name'],
                'file_size' => $result['file_size'],
                'sheets' => $result['sheets'] ?? [],
                'upload_time' => $result['upload_time']
            ]);
        } catch (\Exception $e) {
            return fail('文件上传失败：' . $e->getMessage());
        }
    }

    /**
     * 解析Excel文件
     */
    public function parseExcel()
    {
        $data = $this->request->params([
            ['file_path', ''],
            ['sheet_name', ''],
            ['start_row', 1]
        ]);

        try {
            $service = new RecycleExcelImportService();
            $result = $service->parseExcelFile($data['file_path'], $data['sheet_name'], $data['start_row']);

            return success('Excel解析成功', $result);
        } catch (\Exception $e) {
            return fail('Excel解析失败：' . $e->getMessage());
        }
    }

    /**
     * 预览导入数据
     */
    public function previewImport()
    {
        $data = $this->request->params([
            ['file_path', ''],
            ['mapping', []],
            ['preview_count', 10]
        ]);

        try {
            $service = new RecycleExcelImportService();
            $result = $service->previewImportData($data['file_path'], $data['mapping'], $data['preview_count']);

            return success('数据预览成功', $result);
        } catch (\Exception $e) {
            return fail('数据预览失败：' . $e->getMessage());
        }
    }

    /**
     * 执行导入
     */
    public function importData()
    {
        $data = $this->request->params([
            ['file_path', ''],
            ['mapping', []],
            ['import_mode', 'insert'], // insert: 新增, update: 更新, replace: 替换
            ['skip_errors', true]
        ]);

        try {
            $service = new RecycleExcelImportService();
            $result = $service->importData($data['file_path'], $data['mapping'], $data['import_mode'], $data['skip_errors']);

            return success('数据导入成功', $result);
        } catch (\Exception $e) {
            return fail('数据导入失败：' . $e->getMessage());
        }
    }

    /**
     * 下载导入模板
     */
    public function downloadTemplate()
    {
        try {
            $service = new RecycleExcelImportService();
            $filePath = $service->generateTemplate();
            
            return download($filePath, '设备价格导入模板.xlsx');
        } catch (\Exception $e) {
            return fail('模板下载失败：' . $e->getMessage());
        }
    }

    /**
     * 获取导入历史
     */
    public function getImportHistory()
    {
        $data = $this->request->params([
            ['page', 1],
            ['limit', 20]
        ]);

        try {
            $service = new RecycleExcelImportService();
            $result = $service->getImportHistory($data);

            return success('SUCCESS', $result);
        } catch (\Exception $e) {
            return fail('获取导入历史失败：' . $e->getMessage());
        }
    }

    /**
     * 导出Excel数据
     */
    public function exportData()
    {
        $data = $this->request->params([
            ['brand_id', ''],
            ['device_type', ''],
            ['keyword', '']
        ]);

        try {
            $service = new RecycleExcelService();
            $filePath = $service->exportToExcel($data);
            
            return download($filePath, '设备价格数据_' . date('Y-m-d') . '.xlsx');
        } catch (\Exception $e) {
            return fail('数据导出失败：' . $e->getMessage());
        }
    }
}