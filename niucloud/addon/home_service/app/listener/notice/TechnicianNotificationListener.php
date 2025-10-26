<?php
declare (strict_types=1);

namespace addon\home_service\app\listener\notice;



use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\service\core\notice\CoreNoticeService;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderRefund;

class TechnicianNotificationListener
{

    public function handle($data)
    {
        if (in_array(NoticeDict::TECHNICIAN,$data['identity'])){
            $order = (new Order())->where([['order_id', '=', $data['order_id']]])->findOrEmpty();

            // 根据不同的通知类型构建参数
            $params = $this->buildParams($data['type'], $order);
            $technician_notice_text = NoticeDict::getTechnicianNoticeText($data['notice_source'], $data['type'], $params);

            (new CoreNoticeService())->addTechnicianNotice($data['site_id'],$technician_notice_text,NoticeDict::TECHNICIAN,$data['notice_source'],$data['type'],$data['technician_id'],$data['order_id']);
        }
    }

    /**
     * 根据通知类型构建参数
     * @param string $type 通知类型
     * @param Order $order 订单对象
     * @return array
     */
    protected function buildParams($type, $order)
    {
        $params = [];

        switch ($type) {
            case NoticeDict::GRAB_SUCCESS:
            case NoticeDict::DISPATCH_SUCCESS:
                $time = date('H:i');
                $order_name = $order->order_name ?? '';
                $params = [
                    'time' => $time,
                    'order_name' => $order_name,
                ];
                break;
            case NoticeDict::REMINDER:
                $params = [
                    'order_no' => $order->order_no,
                ];
                break;
            case NoticeDict::ABOUT_TO_TIMEOUT:
            case NoticeDict::REFUND_SUCCESS:
            case NoticeDict::ITEM_PAY_SUCCESS:
            case NoticeDict::REFUND:
            case NoticeDict::REFUND_FAIL:
            case NoticeDict::TIMEOUT:
            default:
                // 这些类型可能不需要额外参数
                break;
        }

        return $params;
    }
}
