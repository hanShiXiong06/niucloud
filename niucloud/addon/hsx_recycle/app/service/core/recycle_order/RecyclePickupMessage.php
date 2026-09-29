<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use app\service\core\member\CoreMemberService;
use app\service\core\weapp\CoreWeappService;
use app\service\core\wechat\CoreWechatService;
use core\exception\CommonException;

/**
 * 仅预约通知使用的发送适配。复用框架凭证/SDK，但必须检查微信 errcode。
 * 通用 NoticeService/TemplateLoader 当前不检查业务错误码，不能作为本通知成功证据。
 */
class RecyclePickupMessage
{
    public const DETAIL_PAGE = 'addon/hsx_recycle/pages/order/detail';

    public function send(int $siteId, string $channel, array $payload, array $config): array
    {
        if (!in_array($channel, ['weapp', 'wechat'], true)) throw new CommonException('未启用预约通知渠道');
        if (empty($config['enabled'])) throw new CommonException('预约通知渠道未启用');
        if (empty($config['template_id']) || empty($config['content'])) {
            throw new CommonException('预约通知模板 ID 或字段映射未配置');
        }
        $memberId = (int)($payload['member_id'] ?? 0);
        if ($memberId <= 0) throw new CommonException('订单未关联会员，无法发送客户通知');
        $member = (new CoreMemberService())->getInfoByMemberId($siteId, $memberId);
        $openid = (string)($member[$channel === 'weapp' ? 'weapp_openid' : 'wx_openid'] ?? '');
        if ($openid === '') throw new CommonException('客户未绑定此微信渠道或未授权，无法发送');

        $data = [];
        foreach ($config['content'] as $mapping) {
            $variable = (string)($mapping['variable'] ?? '');
            $keyword = (string)($mapping['keyword'] ?? '');
            if (!isset(PickupNoticeConfigService::VARIABLES[$variable]) || $keyword === '') {
                throw new CommonException('预约通知字段映射无效');
            }
            $value = trim((string)($payload[$variable] ?? ''));
            if ($value === '') $value = '待确认';
            // 微信 thing/phrase/character_string 字段有长度限制，不截断时间及电话字段。
            $limit = str_starts_with($keyword, 'thing') ? 20 : (str_starts_with($keyword, 'phrase') ? 5 : 0);
            if (str_starts_with($keyword, 'character_string')) $limit = 32;
            if ($limit > 0) $value = mb_substr($value, 0, $limit);
            $data[$keyword] = ['value' => $value];
        }
        $page = self::DETAIL_PAGE . '?id=' . (int)$payload['order_id'];
        $body = ['touser' => $openid, 'template_id' => $config['template_id'], 'data' => $data];
        if ($channel === 'weapp') {
            $body['page'] = $page;
            $response = CoreWeappService::appApiClient($siteId)->postJson('cgi-bin/message/subscribe/send', $body);
        } else {
            $domain = rtrim((string)get_wap_domain($siteId), '/');
            if ($domain === '') throw new CommonException('站点移动端域名未配置，无法生成本人订单链接');
            $body['url'] = $domain . '/' . $page;
            $response = CoreWechatService::appApiClient($siteId)->postJson('cgi-bin/message/template/send', $body);
        }
        $result = is_array($response) ? $response : (is_object($response) && method_exists($response, 'toArray') ? $response->toArray() : null);
        if (!is_array($result) || !array_key_exists('errcode', $result) || !is_numeric($result['errcode'])) {
            throw new CommonException('微信未返回可验证的接口受理结果，状态未知，请勿自动重发');
        }
        if ($result['errcode'] !== 0 && $result['errcode'] !== '0') {
            throw new CommonException('微信拒绝通知：' . (string)$result['errcode'] . ' ' . mb_substr((string)($result['errmsg'] ?? ''), 0, 200));
        }
        return [
            'channel' => $channel, 'send_accepted' => true, 'customer_read' => false,
            'errcode' => 0, 'msgid' => (string)($result['msgid'] ?? ''),
            'message' => '微信接口已受理，不代表客户已收到或已读',
        ];
    }
}
