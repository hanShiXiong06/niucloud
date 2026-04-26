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
use app\service\core\sys\CoreConfigService;
use core\base\BaseApiService;

/**
 * 图书回收系统配置服务
 * Class WjBooksConfigService
 * @package addon\wj_books\app\service\api\wj_books_config
 */
class WjBooksConfigService extends BaseApiService
{
    private const RECYCLE_NOTICE_CONFIG_KEY = 'wj_books_recycle_notice';

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
                'recycle_notice_content' => $this->getDefaultRecycleNoticeContent(),
            ];
        }
        
        // 返回配置数据
        return [
            'min_book_count' => $config['min_book_count'] ?? 5,
            'platform_name' => $config['platform_name'] ?? '二手书回收平台',
            'rejected_book_retrieve_days' => $config['rejected_book_retrieve_days'] ?? 7,
            'recycle_notice_content' => $this->getRecycleNoticeContent(),
        ];
    }

    /**
     * 获取回收须知内容
     * @return string
     */
    private function getRecycleNoticeContent(): string
    {
        $config = (new CoreConfigService())->getConfigValue($this->site_id, self::RECYCLE_NOTICE_CONFIG_KEY);
        $content = $config['content'] ?? '';
        return $content !== '' ? $content : $this->getDefaultRecycleNoticeContent();
    }

    /**
     * 默认回收须知
     * @return string
     */
    private function getDefaultRecycleNoticeContent(): string
    {
        return implode("\n", [
            '请提前整理好书籍，以便快递员上门取件',
            '请确保书籍是正版，且非缺页、水渍等影响阅读的书籍。',
            '平台收货审核完成后，款项将立即到账'
        ]);
    }
}
