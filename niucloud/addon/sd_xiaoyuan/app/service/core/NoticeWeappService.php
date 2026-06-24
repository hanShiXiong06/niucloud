<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\dict\config\ConfigDict;
use app\service\core\member\CoreMemberService;
use app\service\core\notice\CoreNoticeService;
use app\service\core\sys\CoreConfigService;
use app\service\core\weapp\CoreWeappTemplateService;
use think\facade\Log;

/**
 * 小程序订阅消息：配置同步 + 发送
 */
class NoticeWeappService
{
    private $tplMap = [
        'weapp_tpl_order_pay_runner' => 'sd_xiaoyuan_order_pay_runner',
        'weapp_tpl_order_accept_user' => 'sd_xiaoyuan_order_accept_user',
        'weapp_tpl_order_cancel_user' => 'sd_xiaoyuan_order_cancel_user',
    ];

    private $receiverMap = [
        'sd_xiaoyuan_order_pay_runner' => '接单员',
        'sd_xiaoyuan_order_accept_user' => '下单用户',
        'sd_xiaoyuan_order_cancel_user' => '下单用户',
    ];

    /**
     * 写日志，搜索 sd_xiaoyuan_weapp
     */
    private function writeLog(string $step, array $data = [], string $level = 'info')
    {
        $msg = '[sd_xiaoyuan_weapp][' . $step . ']' . json_encode($data, JSON_UNESCAPED_UNICODE);
        Log::write($msg, $level);
    }

    /**
     * 是否开启小程序订阅推送
     */
    public function isEnabled(int $site_id): bool
    {
        if ($site_id <= 0) {
            return false;
        }
        $info = (new CoreConfigService())->getConfig($site_id, ConfigDict::getConfigType());
        $value = is_array($info['value'] ?? null) ? $info['value'] : [];
        return !empty($value['enable_weapp_notice']);
    }

    /**
     * 模板说明（后台展示）
     */
    public function getTplGuide(): array
    {
        $weappDict = include dirname(__DIR__, 2) . '/dict/notice/weapp.php';
        $noticeDict = include dirname(__DIR__, 2) . '/dict/notice/notice.php';
        $list = [];
        foreach ($this->tplMap as $cfgKey => $noticeKey) {
            $weapp = $weappDict[$noticeKey] ?? [];
            $notice = $noticeDict[$noticeKey] ?? [];
            $fields = [];
            foreach ($weapp['content'] ?? [] as $row) {
                $fields[] = [
                    'name' => $row[0] ?? '',
                    'wx_type' => $row[2] ?? '',
                ];
            }
            $list[] = [
                'cfg_key' => $cfgKey,
                'notice_key' => $noticeKey,
                'name' => $notice['name'] ?? '',
                'temp_key' => $weapp['tid'] ?? '',
                'tips' => $weapp['tips'] ?? '',
                'receiver' => $this->receiverMap[$noticeKey] ?? '用户',
                'fields' => $fields,
            ];
        }
        return $list;
    }

    /**
     * 插件配置里的订阅模板ID写入sys_notice
     */
    public function syncToSysNotice(int $site_id)
    {
        if ($site_id <= 0) {
            return;
        }
        $info = (new CoreConfigService())->getConfig($site_id, ConfigDict::getConfigType());
        $value = is_array($info['value'] ?? null) ? $info['value'] : [];
        $enabled = !empty($value['enable_weapp_notice']);
        $noticeService = new CoreNoticeService();
        foreach ($this->tplMap as $cfgKey => $noticeKey) {
            $tplId = trim((string)($value[$cfgKey] ?? ''));
            if (!$enabled || $tplId === '') {
                $noticeService->edit($site_id, $noticeKey, ['is_weapp' => 0]);
                continue;
            }
            $noticeService->edit($site_id, $noticeKey, [
                'is_weapp' => 1,
                'weapp_template_id' => $tplId,
            ]);
        }
    }

    /**
     * 发送小程序订阅消息
     */
    public function send(int $site_id, string $key, array $data)
    {
        $this->writeLog('send_start', ['site_id' => $site_id, 'key' => $key, 'data' => $data]);
        if ($site_id <= 0 || $key === '') {
            return false;
        }
        if (!$this->isEnabled($site_id)) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => '小程序订阅推送未开启']);
            return false;
        }
        $this->syncToSysNotice($site_id);
        $template = (new CoreNoticeService())->getInfo($site_id, $key);
        if (empty($template['is_weapp']) || empty($template['weapp_template_id'])) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => '未启用或无订阅模板ID']);
            return false;
        }
        $result = event('NoticeData', ['site_id' => $site_id, 'key' => $key, 'data' => $data, 'template' => $template]);
        $notice_data = array_values(array_filter($result))[0] ?? [];
        if (empty($notice_data)) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'NoticeData返回为空']);
            return false;
        }
        $vars = $notice_data['vars'] ?? [];
        $to = $notice_data['to'] ?? [];
        $member_id = (int)($to['member_id'] ?? 0);
        if ($member_id <= 0) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'member_id无效']);
            return false;
        }
        $member = (new CoreMemberService())->getInfoByMemberId($site_id, $member_id, 'member_id,member_no,nickname,weapp_openid,wx_openid');
        $openid = (string)($member['weapp_openid'] ?? '');
        if ($openid === '') {
            $this->writeLog('send_skip', [
                'site_id' => $site_id,
                'key' => $key,
                'member_id' => $member_id,
                'member_no' => $member['member_no'] ?? '',
                'nickname' => $member['nickname'] ?? '',
                'msg' => 'weapp_openid为空，需小程序登录并授权订阅',
            ]);
            return false;
        }
        $weapp = $template['weapp'] ?? [];
        $weapp_data = [];
        foreach ($weapp['content'] ?? [] as $row) {
            $search = $row[1] ?? '';
            foreach ($vars as $k => $v) {
                $search = str_replace('{' . $k . '}', (string)$v, $search);
            }
            $weapp_data[$row[2]] = ['value' => $search];
        }
        $page = trim((string)($vars['__weapp_page'] ?? ''));
        $send_res = (new CoreWeappTemplateService())->send(
            $site_id,
            (string)$template['weapp_template_id'],
            $openid,
            $weapp_data,
            $page
        );
        $resArr = is_object($send_res) && method_exists($send_res, 'toArray') ? $send_res->toArray() : (array)$send_res;
        $level = (isset($resArr['errcode']) && (int)$resArr['errcode'] === 0) ? 'info' : 'error';
        $this->writeLog('send_done', [
            'site_id' => $site_id,
            'key' => $key,
            'member_id' => $member_id,
            'openid' => $openid,
            'result' => $resArr,
        ], $level);
        return $resArr;
    }
}
