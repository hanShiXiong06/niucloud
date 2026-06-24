<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use core\base\BaseApiController;
use core\exception\UploadFileException;

/**
 * 文件上传控制器
 */
class Upload extends BaseApiController
{
    /**
     * 允许的文档扩展名
     */
    private $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
    
    /**
     * MIME类型到扩展名映射
     */
    private $mimeToExt = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/vnd.rar' => 'rar',
        'application/x-rar-compressed' => 'rar',
    ];
    
    /**
     * 最大文件大小 50MB
     */
    private $maxSize = 52428800;
    
    /**
     * 文档上传（支持PDF、Word、Excel、PPT等）
     */
    public function document()
    {
        $file = request()->file('file');
        if (empty($file)) {
            return fail('请选择要上传的文件');
        }
        
        // 获取文件信息
        $originalName = $file->getOriginalName();
        $size = $file->getSize();
        $mimeType = $file->getOriginalMime();
        
        // 从文件名获取扩展名
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (empty($ext)) {
            $ext = strtolower($file->getOriginalExtension());
        }
        // 如果扩展名还是空，从MIME类型获取
        if (empty($ext) && isset($this->mimeToExt[$mimeType])) {
            $ext = $this->mimeToExt[$mimeType];
        }
        
        // 验证扩展名
        if (empty($ext) || !in_array($ext, $this->allowedExt)) {
            return fail('不支持的文件类型，允许：' . implode(',', $this->allowedExt));
        }
        
        // 验证文件大小
        if ($size > $this->maxSize) {
            return fail('文件大小超过限制（最大50MB）');
        }
        
        // 生成存储路径
        $site_id = $this->request->siteId();
        $dir = 'upload/addon/sd_xiaoyuan/document/' . $site_id . '/' . date('Ym') . '/' . date('d');
        
        // 创建目录
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // 生成新文件名
        $newFileName = time() . md5($file->getRealPath()) . '_local.' . $ext;
        
        // 移动文件
        $file->move($dir, $newFileName);
        
        // 返回URL
        $url = $dir . '/' . $newFileName;
        
        return success(['url' => $url, 'name' => $originalName]);
    }
}
