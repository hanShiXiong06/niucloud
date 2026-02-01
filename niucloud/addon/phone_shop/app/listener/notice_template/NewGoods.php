<?php
declare (strict_types = 1);

namespace addon\phone_shop\app\listener\notice_template;

use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 新品上架通知
 */
class NewGoods extends BaseNoticeTemplate
{
    private $key = 'phone_shop_new_goods';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $data = $params['data'];
            $site_id = $params['site_id'];
            $wap_domain = get_wap_domain($site_id);

            return $this->toReturn(
                [
                    '__wechat_page' => $wap_domain . '/addon/phone_shop/pages/goods/list', // 公众号跳转链接
                    '__weapp_page' => 'addon/phone_shop/pages/goods/list', // 小程序跳转页面
                    'goods_count' => $data['goods_count'],
                    'goods_names' => $data['goods_names'],
                    'update_time' => $data['update_time'] ?? date('Y-m-d H:i:s'),
                    'site_name' => $data['site_name'] ?? '',
                    'url' => $wap_domain . '/addon/phone_shop/pages/goods/list'
                ],
                [
                    'member_id' => $data['member_id']
                ]
            );
        }
    }
}
