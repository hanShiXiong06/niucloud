<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationConfig;
use addon\recycle\app\dict\quotation\QuotationDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 报价单配置服务
 * Class QuotationConfigService
 * @package addon\recycle\app\service\admin\quotation
 */
class QuotationConfigService extends BaseAdminService
{
    /**
     * @var RecycleQuotationConfig
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleQuotationConfig();
    }

    /**
     * 获取报价单配置列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $field = 'id,site_id,quotation_id,price_name,config_name,default_price_value,default_percentage_value,price_adjustment_type,price_adjustment_value,quotation_background_color,quotation_text_color,is_enable,auto_request,request_time,remark,create_at,update_at';
        
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'price_name', 'is_enable'], $where)
            ->field($field)
            ->order('id desc');
        
        $list = $this->pageQuery($search_model);
        
        // 处理敏感信息（Token和OpenId脱敏显示）
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                if (!empty($item['authorization_token'])) {
                    $item['authorization_token'] = substr($item['authorization_token'], 0, 20) . '...';
                }
                if (!empty($item['open_id'])) {
                    $item['open_id'] = substr($item['open_id'], 0, 10) . '...';
                }
            }
        }
        
        return $list;
    }

    /**
     * 获取报价单配置详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id, ?int $siteId = null): array
    {
        $where = [['id', '=', $id]];
        
        // 如果指定了siteId，使用指定的；否则使用当前服务的site_id（兼容Job调用）
        $targetSiteId = $siteId ?? $this->site_id;
        if (!empty($targetSiteId)) {
            $where[] = ['site_id', '=', $targetSiteId];
        }
        
        $info = $this->model
            ->where($where)
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('配置不存在');
        }

        // Token和OpenId直接返回原始值（明文存储）

        return $info;
    }

    /**
     * 添加报价单配置
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        // 检查是否已存在相同的报价单配置
        $exists = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['quotation_id', '=', $data['quotation_id']],
                ['price_name', '=', $data['price_name']]
            ])
            ->find();

        if (!empty($exists)) {
            throw new CommonException('该报价单配置已存在');
        }

        $data['site_id'] = $this->site_id;
        
        // Token和OpenId直接明文存储，不加密

        $result = $this->model->save($data);
        if (!$result) {
            throw new CommonException('添加失败');
        }

        return $this->model->id;
    }

    /**
     * 编辑报价单配置
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data): bool
    {
        $info = $this->model
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('配置不存在');
        }

        // 如果更新了quotation_id或price_name，检查是否冲突
        if (isset($data['quotation_id']) || isset($data['price_name'])) {
            $quotationId = $data['quotation_id'] ?? $info['quotation_id'];
            $priceName = $data['price_name'] ?? $info['price_name'];
            
            $exists = $this->model
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['quotation_id', '=', $quotationId],
                    ['price_name', '=', $priceName],
                    ['id', '<>', $id]
                ])
                ->find();

            if (!empty($exists)) {
                throw new CommonException('该报价单配置已存在');
            }
        }

        // Token和OpenId直接明文存储，不加密

        return $info->save($data);
    }

    /**
     * 删除报价单配置
     * @param int $id
     * @return bool
     */
    public function del(int $id): bool
    {
        $info = $this->model
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('配置不存在');
        }

        return $info->delete();
    }

    /**
     * 修改状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function modifyStatus(int $id, int $status): bool
    {
        $info = $this->model
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('配置不存在');
        }

        return $info->save(['is_enable' => $status]);
    }

    /**
     * 加密Token
     * @param string $token
     * @return string
     */
    protected function encryptToken(string $token): string
    {
        if (empty($token)) {
            return '';
        }
        
        // 使用系统app_key作为加密密钥
        $key = \think\facade\Env::get('app.app_key', 'niucloud456$%^');
        // 确保密钥长度为16字节（AES-128）
        $key = substr(md5($key), 0, 16);
        
        $encrypted = openssl_encrypt($token, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return base64_encode($encrypted);
    }

    /**
     * 解密Token
     * @param string $encryptedToken
     * @return string
     */
    protected function decryptToken(string $encryptedToken): string
    {
        if (empty($encryptedToken)) {
            return '';
        }
        
        // 使用系统app_key作为解密密钥
        $key = \think\facade\Env::get('app.app_key', 'niucloud456$%^');
        // 确保密钥长度为16字节（AES-128）
        $key = substr(md5($key), 0, 16);
        
        $decrypted = openssl_decrypt(base64_decode($encryptedToken), 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $decrypted ?: '';
    }

    /**
     * 获取解密后的Token（用于请求接口）
     * @param int $id
     * @return array
     */
    public function getDecryptedTokens(int $id): array
    {
        $info = $this->getInfo($id);
        
        return [
            'authorization_token' => !empty($info['authorization_token']) 
                ? $this->decryptToken($info['authorization_token']) 
                : '',
            'open_id' => !empty($info['open_id']) 
                ? $this->decryptToken($info['open_id']) 
                : '',
        ];
    }

    /**
     * 获取启用的报价单配置列表（用于定时任务）
     * @param int|null $siteId 站点ID，为空时使用当前站点
     * @return array
     */
    public function getEnabledConfigs(?int $siteId = null): array
    {
        $siteId = $siteId ?? $this->site_id;
        
        return $this->model
            ->where([
                ['site_id', '=', $siteId],
                ['is_enable', '=', QuotationDict::STATUS_ENABLED],
                ['auto_request', '=', QuotationDict::AUTO_REQUEST_YES]
            ])
            ->select()
            ->toArray();
    }
}

