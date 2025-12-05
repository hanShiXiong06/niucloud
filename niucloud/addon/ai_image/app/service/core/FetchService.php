<?php

namespace addon\ai_image\app\service\core;

use app\service\core\sys\CoreAttachmentService;
use app\service\core\upload\CoreFileService;
use core\exception\UploadFileException;
use Exception;
use think\facade\Log;

/**
 * 上传服务层
 * Class CoreFetchService
 * @package app\service\core\file
 */
class FetchService extends CoreFileService
{

    /**
     * 公用抓取
     */
    public function save(string $url, int $site_id, string $file_dir, $queue = false)
    {
        if (empty($url)) throw new UploadFileException('OSS_FILE_URL_NOT_EXIST');
        $this->upload_driver = $this->driver($site_id);
        $ext = 'png';
        $link = str_replace("." . $ext, "", $url);
        $file_path = $this->upload_driver->createFileName($link, $ext);
        $dir = $this->root_path . '/' . $file_dir . '/' . $file_path;
        //本地驱动拦截
        if (self::$storage_type == 'local' || self::$storage_type == '') {
            if ($queue!=false) {
                $result = $this->fetch($url, 'public/'.$dir);
            }else{
                $result = $this->fetch($url, $dir);
            }

        } else {
            $result = $this->upload_driver->fetch($url, $dir);
        }
        //读取上传附件的信息用于后续得校验和数据写入
        if ($result) {
            if (self::$storage_type == 'ott') {
                $url = $this->upload_driver->getUrl() . $file_path;
            } else {
                $url = $this->upload_driver->getUrl($dir);
            }
            return $url;
        } else {
            throw new UploadFileException($result);
        }
    }
    public function fetch(string $url, ?string $key)
    {
        try {
            if (file_exists($key)) {
                return true; // 文件已存在，无需重复保存
            }
            $directory = dirname($key);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            $content = @file_get_contents($url);
            if (!empty($content)) {
                file_put_contents($key, $content);
                return true;
            } else {
                throw new UploadFileException(203006);
            }
        } catch (Exception $e) {
            Log::write('===AI设计队列存储文件错误===' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            throw new UploadFileException($e->getMessage());
        }
    }
}