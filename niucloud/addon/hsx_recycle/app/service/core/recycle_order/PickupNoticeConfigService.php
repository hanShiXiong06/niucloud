<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\notice\PickupNoticeTemplate;
use app\service\core\notice\CoreNoticeService;
use core\exception\CommonException;

/** 只读框架通知状态，不存储私有模板映射，不提供第二套管理。 */
class PickupNoticeConfigService
{
    public function get(int $siteId): array
    {
        if ($siteId <= 0) throw new CommonException('站点无效');
        $notice = (new CoreNoticeService())->getInfo($siteId, PickupNoticeTemplate::KEY);
        $result = [
            'notice_key' => PickupNoticeTemplate::KEY,
            'switch_managed_by_notice' => true,
            'management' => [
                'notice' => '/setting/notice/template', 'records' => '/setting/notice/records',
                'weapp' => '/channel/weapp/message', 'wechat' => '/channel/wechat/message',
            ],
            'catalog_sync_supported' => false,
            'catalog_sync_message' => PickupNoticeTemplate::PENDING_MESSAGE,
        ];
        foreach (['weapp', 'wechat'] as $channel) {
            $catalogReady = PickupNoticeTemplate::isReady($channel, (array)($notice[$channel] ?? []));
            $enabled = !empty($notice['is_' . $channel]);
            $templateReady = $catalogReady && !empty($notice[$channel . '_template_id']);
            $missing = [];
            if (!$catalogReady) $missing[] = PickupNoticeTemplate::PENDING_MESSAGE;
            elseif (!$templateReady) $missing[] = '尚未在框架渠道消息模板页获取本站模板';
            if (!$enabled) $missing[] = '尚未在设置 → 消息管理启用此渠道';
            $result[$channel] = [
                'enabled' => (int)$enabled, 'catalog_ready' => $catalogReady,
                'template_ready' => $templateReady, 'ready' => $templateReady && $enabled,
                'missing' => $missing,
                'catalog_sync_message' => $catalogReady
                    ? '插件已提供固定模板定义，请到框架渠道消息页获取；是否获取成功以微信当前账号返回为准。'
                    : PickupNoticeTemplate::PENDING_MESSAGE,
                'authorization_note' => $channel === 'weapp'
                    ? '客户须主动订阅且有可用次数；框架执行记录不代表客户收到或已读。'
                    : '客户须关注并绑定当前公众号；框架执行记录不代表客户收到或已读。',
            ];
            $result['catalog_sync_supported'] = $result['catalog_sync_supported'] || $catalogReady;
        }
        if ($result['weapp']['catalog_ready'] && $result['wechat']['catalog_ready']) {
            $result['catalog_sync_message'] = '模板定义由插件固定提供，请在框架渠道消息模板页获取模板，并在消息管理开启。';
        } elseif ($result['weapp']['catalog_ready']) {
            $result['catalog_sync_message'] = '小程序已提供固定模板定义，可到框架小程序订阅消息页获取；公众号模板仍待核实，可保持关闭，不影响已就绪的小程序通知。';
        }
        return $result;
    }
}
