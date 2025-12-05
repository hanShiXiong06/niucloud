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

namespace addon\ai_image\app\service\core;

use app\service\core\upload\CoreFileService;
use core\exception\UploadFileException;
use Exception;

/**
 * 上传服务层
 * Class CoreBase64Service
 * @package app\service\core\file
 */
class CoreBase64Service extends CoreFileService
{

    public function __construct($is_attachment = false)
    {
        parent::__construct($is_attachment);
    }

    /**
     * base64图片上传
     * @param string $content
     * @param int $site_id
     * @param string $file_dir
     * @return array
     * @throws Exception
     */
    public function upload(string $content, int $site_id, string $file_dir, $ext)
    {
        if (empty($content)) throw new UploadFileException('资源不存在');
        $this->upload_driver = $this->driver($site_id);
        $file_path = $this->upload_driver->createFileName(time(), $ext);

        $dir = $this->root_path . '/' . $file_dir . '/' . $file_path;
        $result = $this->upload_driver->base64($content, $dir);
        //读取上传附件的信息用于后续得校验和数据写入
        return $this->upload_driver->getUrl($dir);

    }

}