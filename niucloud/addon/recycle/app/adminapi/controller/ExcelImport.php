<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use addon\recycle\app\service\admin\ExcelImportService;
use core\base\BaseAdminController;
use think\Response;

/**
 * Excel导入控制器
 * Class ExcelImport
 * @package addon\recycle\app\adminapi\controller
 */
class ExcelImport extends BaseAdminController
{
    /**
     * 上传并导入Excel文件
     * @return Response
     */
    public function upload()
    {
        try {
            // 检查是否有文件上传
        
            // 获取上传的文件信息
            $file = $this->request->file('file');
            
            if (!$file || !$file->isValid()) {
                return fail('文件上传失败，请重试');
            }

            // 验证文件类型
            $allowedTypes = ['xlsx', 'xls'];
            $extension = strtolower($file->getOriginalExtension());
            
            if (!in_array($extension, $allowedTypes)) {
                return fail('只支持Excel文件格式 (.xlsx, .xls)');
            }

            // 验证文件大小 (最大20MB)
            $maxSize = 20 * 1024 * 1024;
            if ($file->getSize() > $maxSize) {
                return fail('文件大小不能超过20MB');
            }

            // 保存文件到临时目录
            $saveName = 'price_import_' . date('YmdHis') . '.' . $extension;
            $uploadPath = 'upload/temp/';
            
            // 确保目录存在
            $fullPath = public_path() . $uploadPath;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            $info = $file->move($fullPath, $saveName);
            
            if (!$info) {
                return fail('文件保存失败');
            }

            $filePath = $fullPath . $saveName;

            // 执行导入
            $service = new ExcelImportService();
            $result = $service->executeImport($filePath);

            // 删除临时文件
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return success($result['message'] ?? '导入成功', $result);

        } catch (\Exception $e) {
            return fail('导入失败：' . $e->getMessage());
        }
    }

    /**
     * 获取导入历史记录
     * @return Response
     */
    public function history()
    {
        $data = $this->request->params([
            ['import_batch', ''],
            ['file_name', ''],
            ['status', '']
        ]);

        $service = new ExcelImportService();
        $list = $service->getImportHistory($data);

        return success('获取成功', $list);
    }

    /**
     * 获取导入记录详情
     * @return Response
     */
    public function detail()
    {
        $id = $this->request->param('id/d', 0);
        
        if (empty($id)) {
            return fail('参数错误');
        }

        try {
            $service = new ExcelImportService();
            $info = $service->getImportDetail($id);
            
            return success('获取成功', $info);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
} 