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

namespace addon\wj_books\app\service\api\wj_books_config;

use addon\wj_books\app\model\wj_books_config\WjBooksConfig;
use core\base\BaseApiService;

/**
 * 图书回收系统配置服务
 * Class WjBooksConfigService
 * @package addon\wj_books\app\service\api\wj_books_config
 */
class WjBooksConfigService extends BaseApiService
{
    /**
     * @var WjBooksConfig
     */
    protected $configModel;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->configModel = new WjBooksConfig();
    }
    
    /**
     * 获取系统配置
     * @return array
     */
    public function getConfig()
    {
        // 获取当前站点的配置
        $config = $this->configModel->where([['site_id', '=', $this->site_id]])->find();
        
        if (empty($config)) {
            // 如果没有配置，返回默认值
            return [
                'min_book_count' => 5,
                'platform_name' => '二手书回收平台',
                'rejected_book_retrieve_days' => 7,
            ];
        }
        
        // 返回配置数据
        return [
            'min_book_count' => $config['min_book_count'] ?? 5,
            'platform_name' => $config['platform_name'] ?? '二手书回收平台',
            'rejected_book_retrieve_days' => $config['rejected_book_retrieve_days'] ?? 7,
        ];
    }
} 