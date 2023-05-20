<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace app\service\core\upload;

use app\enum\sys\FileEnum;
use core\base\BaseCoreService;
use core\upload\FileDriver;
use core\upload\UploadLoader;

/**
 * 上传服务层
 * Class CoreFileService
 * @package app\service\core\file
 */
class CoreFileService extends BaseCoreService
{

    protected $root_path = 'upload';

    public function __construct(protected $is_attachment = false)
    {
        parent::__construct();
    }

    /**
     * @var FileDriver
     */
    protected $upload_driver;
    protected static $storage_type;
    /**
     * 实例化上传引擎
     * @param int $site_id
     * @param bool $is_local
     * @return FileDriver
     */
    public function driver(int $site_id, bool $is_local = false){
        if($is_local){
            self::$storage_type = FileEnum::LOCAL;
        }else{
            $core_storage_service = new CoreStorageService();
            $storage_config = $core_storage_service->getDefaultStorage($this->request->defaultSiteId());
            self::$storage_type = $storage_config['storage_type'];
        }
        //查询启用的上传方式
        return new UploadLoader(self::$storage_type, $storage_config ?? []);
    }
}