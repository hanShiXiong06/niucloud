<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\notice_template;

use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\dict\notice\PickupNoticeTemplate;
use app\listener\notice_template\BaseNoticeTemplate;
use core\exception\CommonException;

/** notice 标准业务数据扩展：站点与订单共同限定收件人，不接受载荷指定会员。 */
class PickupUpdate extends BaseNoticeTemplate
{
    public const STATES = PickupNoticeTemplate::STATES;

    public function handle(array $params): ?array
    {
        if (($params['key'] ?? '') !== PickupNoticeTemplate::KEY) return null;
        $siteId = (int)($params['site_id'] ?? 0);
        $payload = $params['data'] ?? [];
        if (!is_array($payload)) throw new CommonException('预约通知数据格式错误');
        $orderId = (int)($payload['order_id'] ?? 0);
        $state = $payload['state'] ?? '';
        if ($siteId <= 0 || $orderId <= 0) throw new CommonException('预约通知缺少有效站点或订单');
        if (!is_string($state) || !isset(self::STATES[$state])) throw new CommonException('当前预约状态不发送客户通知');
        $order = RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $orderId]])
            ->field('id,site_id,member_id,order_no')->findOrEmpty()->toArray();
        if (!$order) throw new CommonException('当前站点回收订单不存在');
        if ((int)$order['member_id'] <= 0) throw new CommonException('订单未关联会员，无法发送客户通知');
        $vars = [];
        foreach (PickupNoticeTemplate::VARIABLES as $key => $label) {
            $vars[$key] = is_scalar($payload[$key] ?? '') ? trim((string)($payload[$key] ?? '')) : '';
        }
        $vars['order_no'] = (string)$order['order_no'];
        $vars['state_name'] = self::STATES[$state];
        if ($vars['message'] === '') $vars['message'] = '预约取件状态已更新，请查看订单详情。';
        // 短字段由业务状态生成，不能信任载荷覆盖，也不把供应商长错误/电话拼进微信短字段。
        $vars['weapp_state'] = PickupNoticeTemplate::WEAPP_STATES[$state];
        $tip = PickupNoticeTemplate::WEAPP_TIPS[$state];
        $carrier = trim((string)preg_replace('/\s+/u', ' ', $vars['carrier_name']));
        if ($carrier !== '' && mb_strlen($carrier . '：' . $tip) <= 20) $tip = $carrier . '：' . $tip;
        $vars['weapp_tip'] = $tip;
        $page = PickupNoticeTemplate::DETAIL_PAGE . '?id=' . $orderId;
        $domain = rtrim((string)get_wap_domain($siteId), '/');
        $vars['__weapp_page'] = $page;
        $vars['__wechat_page'] = $domain === '' ? '' : $domain . '/' . $page;
        return $this->toReturn($vars, ['member_id' => (int)$order['member_id']]);
    }
}
