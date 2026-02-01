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

namespace addon\phone_shop\app\service\admin\sys;

use app\service\core\weapp\CoreWeappService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 小程序 Short Link 服务类
 * Class ShortLinkService
 * @package addon\phone_shop\app\service\admin\sys
 */
class ShortLinkService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 生成小程序 Short Link
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function generateShortLink($data)
    {
        $page_url = $data['page_url'] ?? '';
        $page_title = $data['page_title'] ?? '';
        $is_permanent = $data['is_permanent'] ?? false;

        if (empty($page_url)) {
            throw new CommonException('页面路径不能为空');
        }

        try {
            // 使用 CoreWeappService 获取小程序 API 客户端
            $client = CoreWeappService::appApiClient($this->site_id);

            // 调用微信 API 生成 Short Link
            $response = $client->postJson('/wxa/genwxashortlink', [
                'page_url' => $page_url,
                'page_title' => $page_title,
                'is_permanent' => $is_permanent
            ]);

            // 检查响应
            if ($response->isFailed()) {
                $errcode = $response['errcode'] ?? 'unknown';
                $errmsg = $response['errmsg'] ?? '未知错误';
                throw new CommonException("生成 Short Link 失败：errcode:{$errcode}, errmsg:{$errmsg}");
            }

            // 返回生成的短链接
            return $response['link'] ?? '';

        } catch (\Exception $e) {
            throw new CommonException('生成 Short Link 异常：' . $e->getMessage());
        }
    }

    /**
     * 批量生成商品的 Short Link
     * @param array $goods_list
     * @return array
     */
    public function batchGenerateGoodsShortLink($goods_list)
    {
        $result = [];

        foreach ($goods_list as $goods) {
            try {
                // 构建页面路径
                $page_url = "addon/phone_shop/pages/goods/detail?goods_id={$goods['goods_id']}";

                // 构建页面标题（商品名称 + 副标题，最多20字符）
                $title = $goods['goods_name'];
                if (!empty($goods['sub_title'])) {
                    $title .= ' ' . $goods['sub_title'];
                }
                $page_title = mb_substr($title, 0, 20, 'UTF-8');

                // 生成 Short Link
                $short_link = $this->generateShortLink([
                    'page_url' => $page_url,
                    'page_title' => $page_title,
                    'is_permanent' => false // 短期有效（30天）
                ]);

                $result[] = [
                    'goods_id' => $goods['goods_id'],
                    'goods_name' => $goods['goods_name'],
                    'sub_title' => $goods['sub_title'] ?? '',
                    'short_link' => $short_link,
                    'success' => true
                ];

            } catch (\Exception $e) {
                $result[] = [
                    'goods_id' => $goods['goods_id'],
                    'goods_name' => $goods['goods_name'],
                    'sub_title' => $goods['sub_title'] ?? '',
                    'short_link' => '',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }
}
