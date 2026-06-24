<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\dict\config\ConfigDict;
use app\service\core\member\CoreMemberService;
use app\service\core\notice\CoreNoticeBindMerchantService;
use app\service\core\notice\CoreNoticeService;
use app\service\core\sys\CoreConfigService;
use app\service\core\sys\CoreSysConfigService;
use app\service\core\weapp\CoreWeappConfigService;
use app\service\core\wechat\CoreWechatConfigService;
use think\facade\Cache;
use think\facade\Log;

/**
 * 公众号模板：配置同步 + 稳定版token发送
 */
class NoticeWechatService
{
    private $tplMap = [
        'wechat_tpl_order_pay_runner' => 'sd_xiaoyuan_order_pay_runner',
        'wechat_tpl_runner_apply' => 'sd_xiaoyuan_runner_apply',
        'wechat_tpl_order_accept_user' => 'sd_xiaoyuan_order_accept_user',
    ];

    /**
     * 写日志到 niucloud/runtime/log，搜索 sd_xiaoyuan_wechat
     */
    private function writeLog(string $step, array $data = [], string $level = 'info')
    {
        $msg = '[sd_xiaoyuan_wechat][' . $step . ']' . json_encode($data, JSON_UNESCAPED_UNICODE);
        Log::write($msg, $level);
    }

    /**
     * 是否开启公众号推送
     */
    public function isEnabled(int $site_id): bool
    {
        if ($site_id <= 0) {
            $this->writeLog('isEnabled', ['site_id' => $site_id, 'enabled' => false, 'msg' => 'site_id无效']);
            return false;
        }
        $info = (new CoreConfigService())->getConfig($site_id, ConfigDict::getConfigType());
        $value = is_array($info['value'] ?? null) ? $info['value'] : [];
        $enabled = !empty($value['enable_wechat_notice']);
        $this->writeLog('isEnabled', ['site_id' => $site_id, 'enabled' => $enabled]);
        return $enabled;
    }

    /**
     * 模板配置说明（给后台展示用）
     */
    public function getTplGuide(): array
    {
        $wechatDict = include dirname(__DIR__, 2) . '/dict/notice/wechat.php';
        $noticeDict = include dirname(__DIR__, 2) . '/dict/notice/notice.php';
        $list = [];
        foreach ($this->tplMap as $cfgKey => $noticeKey) {
            $wechat = $wechatDict[$noticeKey] ?? [];
            $notice = $noticeDict[$noticeKey] ?? [];
            $fields = [];
            foreach ($wechat['content'] ?? [] as $row) {
                $fields[] = [
                    'name' => $row[0] ?? '',
                    'wx_type' => $row[2] ?? '',
                ];
            }
            $list[] = [
                'cfg_key' => $cfgKey,
                'notice_key' => $noticeKey,
                'name' => $notice['name'] ?? '',
                'temp_key' => $wechat['temp_key'] ?? '',
                'tips' => $wechat['tips'] ?? '',
                'receiver' => $noticeKey === 'sd_xiaoyuan_runner_apply' ? '配置的管理员会员' : '会员',
                'fields' => $fields,
            ];
        }
        return $list;
    }

    /**
     * 解析审核管理员会员ID
     */
    public function parseAdminMemberIds(int $site_id): array
    {
        if ($site_id <= 0) {
            $this->writeLog('parseAdminMemberIds', ['site_id' => $site_id, 'ids' => []]);
            return [];
        }
        $info = (new CoreConfigService())->getConfig($site_id, ConfigDict::getConfigType());
        $value = is_array($info['value'] ?? null) ? $info['value'] : [];
        $ids_str = trim((string)($value['wechat_runner_apply_admin_ids'] ?? ''));
        if ($ids_str === '') {
            $this->writeLog('parseAdminMemberIds', ['site_id' => $site_id, 'ids' => [], 'msg' => '未配置管理员ID']);
            return [];
        }
        $ids = array_filter(array_map('intval', explode(',', str_replace('，', ',', $ids_str))));
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
        $this->writeLog('parseAdminMemberIds', ['site_id' => $site_id, 'ids' => $ids]);
        return $ids;
    }

    /**
     * 后台接单员审核列表链接
     */
    public function getAdminRunnerListUrl(int $site_id): string
    {
        $path = '/admin/site/sd_xiaoyuan/runner/list';
        if ($site_id <= 0) {
            return $path;
        }
        $domain = (new CoreSysConfigService())->getSceneDomain($site_id);
        $host = rtrim((string)($domain['service_domain'] ?? ''), '/');
        if ($host === '') {
            $host = rtrim((string)($domain['wap_domain'] ?? ''), '/');
        }
        $url = $host !== '' ? ($host . $path) : $path;
        $this->writeLog('getAdminRunnerListUrl', ['site_id' => $site_id, 'url' => $url]);
        return $url;
    }

    /**
     * 插件配置里的私有模板ID写入sys_notice
     */
    public function syncToSysNotice(int $site_id)
    {
        if ($site_id <= 0) {
            $this->writeLog('syncToSysNotice', ['site_id' => $site_id, 'msg' => 'site_id无效']);
            return;
        }
        $info = (new CoreConfigService())->getConfig($site_id, ConfigDict::getConfigType());
        $value = is_array($info['value'] ?? null) ? $info['value'] : [];
        $enabled = !empty($value['enable_wechat_notice']);
        $this->writeLog('syncToSysNotice_start', ['site_id' => $site_id, 'enabled' => $enabled]);
        $noticeService = new CoreNoticeService();
        foreach ($this->tplMap as $cfgKey => $noticeKey) {
            $tplId = trim((string)($value[$cfgKey] ?? ''));
            if (!$enabled || $tplId === '') {
                $noticeService->edit($site_id, $noticeKey, ['is_wechat' => 0]);
                $this->writeLog('syncToSysNotice_item', [
                    'site_id' => $site_id,
                    'notice_key' => $noticeKey,
                    'is_wechat' => 0,
                    'msg' => !$enabled ? '总开关关闭' : '模板ID为空',
                ]);
                continue;
            }
            $noticeService->edit($site_id, $noticeKey, [
                'is_wechat' => 1,
                'wechat_template_id' => $tplId,
            ]);
            $this->writeLog('syncToSysNotice_item', [
                'site_id' => $site_id,
                'notice_key' => $noticeKey,
                'is_wechat' => 1,
                'wechat_template_id' => $tplId,
            ]);
        }
        $this->writeLog('syncToSysNotice_done', ['site_id' => $site_id]);
    }

    /**
     * 发送公众号模板（稳定版token，不走EasyWeChat缓存token）
     */
    public function send(int $site_id, string $key, array $data)
    {
        $this->writeLog('send_start', ['site_id' => $site_id, 'key' => $key, 'data' => $data]);
        if ($site_id <= 0 || $key === '') {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'site_id或key无效']);
            return false;
        }
        if (!$this->isEnabled($site_id)) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => '公众号推送未开启']);
            return false;
        }
        $this->syncToSysNotice($site_id);
        $template = (new CoreNoticeService())->getInfo($site_id, $key);
        $this->writeLog('send_template', [
            'site_id' => $site_id,
            'key' => $key,
            'is_wechat' => $template['is_wechat'] ?? 0,
            'wechat_template_id' => $template['wechat_template_id'] ?? '',
            'receiver_type' => $template['receiver_type'] ?? 0,
        ]);
        if (empty($template['is_wechat']) || empty($template['wechat_template_id'])) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => '未启用或无模板ID']);
            return false;
        }
        $result = event('NoticeData', ['site_id' => $site_id, 'key' => $key, 'data' => $data, 'template' => $template]);
        $notice_data = array_values(array_filter($result))[0] ?? [];
        $this->writeLog('send_notice_data', [
            'site_id' => $site_id,
            'key' => $key,
            'has_data' => !empty($notice_data),
            'vars_keys' => array_keys($notice_data['vars'] ?? []),
            'to' => $notice_data['to'] ?? [],
        ]);
        if (empty($notice_data)) {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'NoticeData事件返回为空']);
            return false;
        }
        $vars = $notice_data['vars'] ?? [];
        $to = $notice_data['to'] ?? [];
        $openid = '';
        if ((int)($template['receiver_type'] ?? 1) === 1) {
            $member_id = (int)($to['member_id'] ?? 0);
            $this->writeLog('send_receiver_member', ['site_id' => $site_id, 'key' => $key, 'member_id' => $member_id]);
            if ($member_id <= 0) {
                $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'member_id无效']);
                return false;
            }
            $member = (new CoreMemberService())->getInfoByMemberId($site_id, $member_id);
            $openid = (string)($member['wx_openid'] ?? '');
            $this->writeLog('send_member_openid', [
                'site_id' => $site_id,
                'key' => $key,
                'member_id' => $member_id,
                'has_openid' => $openid !== '',
            ]);
        } else {
            $merchant_id = (int)($to['merchant_id'] ?? 0);
            $this->writeLog('send_receiver_merchant', ['site_id' => $site_id, 'key' => $key, 'merchant_id' => $merchant_id]);
            if ($merchant_id <= 0) {
                $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'merchant_id无效']);
                return false;
            }
            $merchant = (new CoreNoticeBindMerchantService())->getInfo($merchant_id);
            $openid = (string)($merchant['wechat_openid'] ?? '');
            $this->writeLog('send_merchant_openid', [
                'site_id' => $site_id,
                'key' => $key,
                'merchant_id' => $merchant_id,
                'has_openid' => $openid !== '',
            ]);
        }
        if ($openid === '') {
            $this->writeLog('send_skip', ['site_id' => $site_id, 'key' => $key, 'msg' => 'openid为空，需微信H5登录']);
            return false;
        }
        $wechat = $template['wechat'] ?? [];
        $wechat_data = [];
        foreach ($wechat['content'] ?? [] as $row) {
            $search = $row[1] ?? '';
            foreach ($vars as $k => $v) {
                $search = str_replace('{' . $k . '}', (string)$v, $search);
            }
            $wechat_data[$row[2]] = ['value' => $search];
        }
        $url = (string)($vars['__wechat_page'] ?? '');
        $payload = [
            'touser' => $openid,
            'template_id' => $template['wechat_template_id'],
            'url' => $url,
            'data' => $wechat_data,
        ];
        $weapp_page = trim((string)($vars['__weapp_page'] ?? ''));
        if ($weapp_page !== '') {
            $appid = (string)((new CoreWeappConfigService())->getWeappConfig($site_id)['app_id'] ?? '');
            if ($appid !== '') {
                $payload['miniprogram'] = [
                    'appid' => $appid,
                    'pagepath' => $weapp_page,
                ];
            }
        }
        $this->writeLog('send_payload', [
            'site_id' => $site_id,
            'key' => $key,
            'openid' => $openid,
            'template_id' => $template['wechat_template_id'],
            'url' => $url,
            'data' => $wechat_data,
        ]);
        $send_res = $this->postTemplate($site_id, $payload);
        if (isset($send_res['errcode']) && (int)$send_res['errcode'] === 40001) {
            $this->writeLog('send_token_40001', ['site_id' => $site_id, 'key' => $key, 'msg' => 'token失效，强制刷新重试']);
            $send_res = $this->postTemplate($site_id, $payload, true);
        }
        $level = (isset($send_res['errcode']) && (int)$send_res['errcode'] === 0) ? 'info' : 'error';
        $this->writeLog('send_done', [
            'site_id' => $site_id,
            'key' => $key,
            'openid' => $openid,
            'result' => $send_res,
        ], $level);
        return $send_res;
    }

    /**
     * 调微信发模板
     */
    private function postTemplate(int $site_id, array $payload, bool $force_refresh = false)
    {
        $this->writeLog('postTemplate_start', [
            'site_id' => $site_id,
            'force_refresh' => $force_refresh,
            'touser' => $payload['touser'] ?? '',
            'template_id' => $payload['template_id'] ?? '',
        ]);
        $token = $this->getStableToken($site_id, $force_refresh);
        if ($token === '') {
            $this->writeLog('postTemplate_fail', ['site_id' => $site_id, 'msg' => '获取stable_token失败'], 'error');
            return ['errcode' => -1, 'errmsg' => '获取stable_token失败'];
        }
        $apiUrl = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 15,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents($apiUrl, false, $ctx);
        $res = $raw ? json_decode($raw, true) : [];
        $res = is_array($res) ? $res : ['errcode' => -1, 'errmsg' => '请求失败'];
        $level = (isset($res['errcode']) && (int)$res['errcode'] === 0) ? 'info' : 'error';
        $this->writeLog('postTemplate_result', ['site_id' => $site_id, 'result' => $res, 'raw_len' => strlen((string)$raw)], $level);
        return $res;
    }

    /**
     * 获取稳定版access_token
     */
    private function getStableToken(int $site_id, bool $force_refresh = false): string
    {
        $cacheKey = 'sd_xiaoyuan_wechat_stable_token_' . $site_id;
        if (!$force_refresh) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                $this->writeLog('getStableToken_cache', ['site_id' => $site_id, 'msg' => '命中缓存']);
                return (string)$cached;
            }
        } else {
            Cache::delete($cacheKey);
            $this->writeLog('getStableToken_refresh', ['site_id' => $site_id, 'msg' => '强制刷新token']);
        }
        $cfg = (new CoreWechatConfigService())->getWechatConfig($site_id);
        $app_id = $cfg['app_id'] ?? '';
        $secret = $cfg['app_secret'] ?? '';
        if ($app_id === '' || $secret === '') {
            $this->writeLog('getStableToken_fail', [
                'site_id' => $site_id,
                'has_app_id' => $app_id !== '',
                'has_secret' => $secret !== '',
                'msg' => '公众号AppID或Secret未配置',
            ], 'error');
            return '';
        }
        $body = json_encode([
            'grant_type' => 'client_credential',
            'appid' => $app_id,
            'secret' => $secret,
            'force_refresh' => $force_refresh,
        ], JSON_UNESCAPED_UNICODE);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 15,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents('https://api.weixin.qq.com/cgi-bin/stable_token', false, $ctx);
        $res = $raw ? json_decode($raw, true) : [];
        if (empty($res['access_token'])) {
            $this->writeLog('getStableToken_fail', ['site_id' => $site_id, 'result' => $res], 'error');
            return '';
        }
        $expires = (int)($res['expires_in'] ?? 7200);
        if ($expires > 300) {
            Cache::set($cacheKey, $res['access_token'], $expires - 300);
        }
        $this->writeLog('getStableToken_ok', ['site_id' => $site_id, 'expires_in' => $expires]);
        return (string)$res['access_token'];
    }
}
