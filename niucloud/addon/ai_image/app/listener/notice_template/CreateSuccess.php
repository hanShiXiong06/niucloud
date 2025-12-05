<?php
declare (strict_types = 1);

namespace addon\ai_image\app\listener\notice_template;
use addon\ai_image\app\model\aiimagecreate\AiimageCreate;
use addon\ai_image\app\model\aiimageorder\AiimageOrder;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单支付通知
 */
class CreateSuccess extends BaseNoticeTemplate
{
    private $key = 'ai_image_create_success';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new AiimageCreate())->where(['id'=>$params['data']['id']])->findOrEmpty();
            if (!$order->isEmpty()) {
                $wap_domain = get_wap_domain($order['site_id']);
                return $this->toReturn(
                    [
                        '__wechat_page' => $wap_domain . '/addon/ai_image/pages/list' ,//模板消息链接
                        '__weapp_page' => 'addon/ai_image/pages/list',//小程序链接
                        'body' =>'视频生成成功',
                        'update_time'=>date('Y-m-d'),
                        'create_time' => $order['create_time'],
                    ],
                    [
                        'member_id' => $order['member_id']
                    ]
                );
            }
        }
    }
}
