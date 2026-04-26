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

namespace addon\wj_books\app\service\admin\wj_books_config;

use addon\wj_books\app\model\wj_books_config\WjBooksConfig;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use think\facade\Cache;
use think\facade\Validate;

/**
 * 旧书回收系统配置服务
 * Class WjBooksConfigService
 * @package addon\wj_books\app\service\admin\wj_books_config
 */
class WjBooksConfigService extends BaseAdminService
{
    private const RECYCLE_NOTICE_CONFIG_KEY = 'wj_books_recycle_notice';

    /**
     * 缓存前缀
     * @var string
     */
    protected $cache_prefix = 'wj_books_config_';

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksConfig();
    }

    /**
     * 获取配置
     * @return array
     */
    public function getConfig()
    {
        // 尝试从缓存获取
        $cache_key = $this->cache_prefix . $this->site_id;
        $config = Cache::get($cache_key);
        
        if (!$config) {
            // 从数据库获取
            $config = $this->model->where([['site_id', '=', $this->site_id]])->find();
            
            if (empty($config)) {
                // 如果没有配置，创建默认配置
                $config = $this->createDefaultConfig();
            } else {
                $config = $config->toArray();
            }
            
            // 缓存配置，有效期1小时
            Cache::set($cache_key, $config, 3600);
        }

        $config['recycle_notice_content'] = $this->getRecycleNoticeContent();
        
        return $config;
    }

    /**
     * 更新配置
     * @param array $data
     * @return bool
     */
    public function updateConfig(array $data)
    {
        // 验证数据
        validate(\addon\wj_books\app\validate\wj_books_config\WjBooksConfig::class)
            ->scene('update')
            ->check($data);

        $recycleNoticeContent = $data['recycle_notice_content'] ?? '';
        unset($data['recycle_notice_content']);
        
        // 查询是否已存在配置
        $config = $this->model->where([['site_id', '=', $this->site_id]])->find();
        
        if (empty($config)) {
            // 不存在则创建
            $data['site_id'] = $this->site_id;
            $result = $this->model->create($data);
        } else {
            // 存在则更新
            $result = $this->model->where([['site_id', '=', $this->site_id]])->update($data);
        }
        
        // 清除缓存
        $this->clearCache();

        (new CoreConfigService())->setConfig($this->site_id, self::RECYCLE_NOTICE_CONFIG_KEY, [
            'content' => $recycleNoticeContent
        ]);
        
        return $result !== false;
    }

    /**
     * 创建默认配置
     * @return array
     */
    private function createDefaultConfig()
    {
        $data = [
            'site_id' => $this->site_id,
            'platform_name' => '二手书回收平台',
            'min_book_count' => 5,
            'rejected_book_retrieve_days' => 2,
            'price_adjust_rate' => 0,
            'book_api_enabled' => 1,
            'book_api_provider' => '0',
            'book_api_key' => '',
            'yunyang_appid' => '',
            'yunyang_app_secret' => '',
            'yunyang_callback_url' => '',
            'yunyang_channel_subtag' => '京东',
            'yunyang_auto_order' => 1,
            'express_receiver_name' => '',
            'express_receiver_mobile' => '',
            'express_receiver_province' => '',
            'express_receiver_city' => '',
            'express_receiver_county' => '',
            'express_receiver_town' => '',
            'express_receiver_location' => '',
            'create_time' => date('Y-m-d H:i:s')
        ];
        
        $this->model->insert($data);
        
        return $data;
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

    /**
     * 验证配置数据
     * @param array $data
     * @return bool
     */
    private function validateConfig(array $data)
    {
        // 这里可以添加额外的验证逻辑
        return true;
    }

    /**
     * 清除缓存
     * @return bool
     */
    private function clearCache()
    {
        $cache_key = $this->cache_prefix . $this->site_id;
        return Cache::delete($cache_key);
    }
} 
