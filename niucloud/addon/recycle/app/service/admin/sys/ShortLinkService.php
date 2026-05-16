<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\sys;

use app\service\core\weapp\CoreWeappService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 回收订单小程序 Short Link 服务类
 * Class ShortLinkService
 * @package addon\recycle\app\service\admin\sys
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
     * @throws CommonException
     */
    public function generateShortLink(array $data): string
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

    /**
     * 生成回收订单分享链接
     * @param array $data  包含 order_id, order_no 等
     * @return array
     * @throws CommonException
     */
    public function generateOrderShortLink(array $data): array
    {
        $order_id = $data['order_id'] ?? 0;
        $order_no = $data['order_no'] ?? '';

        if (empty($order_id)) {
            throw new CommonException('订单ID不能为空');
        }

        $page_url = "addon/recycle/pages/order/detail?id={$order_id}";
        $page_title = $order_no ? "回收订单{$order_no}" : "回收订单详情";

        $short_link = $this->generateShortLink([
            'page_url'     => $page_url,
            'page_title'   => $page_title,
            'is_permanent' => false
        ]);

        return [
            'order_id'   => $order_id,
            'order_no'   => $order_no,
            'short_link' => $short_link,
            'page_url'   => $page_url
        ];
    }
}
