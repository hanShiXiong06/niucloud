<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\api\goods;

use app\service\core\weapp\CoreWeappService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 商品分享服务类
 * Class ShareService
 * @package addon\phone_shop\app\service\api
 */
class ShareService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 生成商品分享短链接
     * @param array $data
     * @return array
     * @throws CommonException
     */
    public function generateGoodsShareLink(array $data): array
    {
        $goods_id = $data['goods_id'] ?? 0;
        $share_user_id = $data['share_user_id'] ?? 0;

        if (empty($goods_id)) {
            throw new CommonException('商品ID不能为空');
        }

        // 构建页面路径
        $page_url = "addon/phone_shop/pages/goods/detail?goods_id={$goods_id}";

        if (!empty($share_user_id)) {
            $page_url .= "&share_user_id={$share_user_id}";
        }

        $page_title = "商品详情";

        try {
            $short_link = $this->generateShortLink([
                'page_url'     => $page_url,
                'page_title'   => $page_title,
                'is_permanent' => false
            ]);

            return [
                'goods_id'      => $goods_id,
                'share_user_id' => $share_user_id,
                'scheme'        => $short_link,  // 返回 scheme 字段（前端期望的字段名）
                'short_link'    => $short_link,
                'page_url'      => $page_url
            ];

        } catch (\Exception $e) {
            // 如果生成失败，返回普通路径
            return [
                'goods_id'      => $goods_id,
                'share_user_id' => $share_user_id,
                'scheme'        => $page_url,
                'short_link'    => '',
                'page_url'      => $page_url
            ];
        }
    }

    /**
     * 生成小程序 Short Link
     * @param array $data
     * @return string
     * @throws CommonException
     */
    private function generateShortLink(array $data): string
    {
        $page_url = $data['page_url'] ?? '';
        $page_title = $data['page_title'] ?? '';
        $is_permanent = $data['is_permanent'] ?? false;

        if (empty($page_url)) {
            throw new CommonException('页面路径不能为空');
        }

        try {
            $client = CoreWeappService::appApiClient($this->site_id);

            $response = $client->postJson('/wxa/genwxashortlink', [
                'page_url'     => $page_url,
                'page_title'   => mb_substr($page_title, 0, 20, 'UTF-8'),
                'is_permanent' => $is_permanent
            ]);

            if ($response->isFailed()) {
                $errcode = $response['errcode'] ?? 'unknown';
                $errmsg = $response['errmsg'] ?? '未知错误';
                throw new CommonException("生成 Short Link 失败：errcode:{$errcode}, errmsg:{$errmsg}");
            }

            return $response['link'] ?? '';

        } catch (\Exception $e) {
            throw new CommonException('生成 Short Link 异常：' . $e->getMessage());
        }
    }
}
