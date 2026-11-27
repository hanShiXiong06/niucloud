<?php
declare(strict_types=1);

namespace PhoneShopNoticeProbe;

use addon\phone_shop\app\model\goods\GoodsSubscription;
use addon\phone_shop\app\service\core\goods\CoreGoodsNoticeService;
use app\model\member\Member;
use app\service\core\notice\CoreNoticeService;
use app\service\core\weapp\CoreWeappService;
use EasyWeChat\Kernel\Config;

// CLI only: never expose a public endpoint that can send notifications.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

function inspect(int $siteId, int $memberId, bool $arrivalTemplate = false): array
{
    $notice = new CoreGoodsNoticeService();
    $capability = $notice->capability($siteId);
    $member = (new Member())->where([
        ['site_id', '=', $siteId], ['member_id', '=', $memberId],
    ])->field('member_id,site_id,weapp_openid')->findOrEmpty()->toArray();
    $subscriptions = (new GoodsSubscription())->where([
        ['site_id', '=', $siteId], ['member_id', '=', $memberId],
    ])->field('subscription_id,subscription_name,status,rule_json,create_time,update_time')
        ->order('subscription_id desc')->select()->toArray();

    $rows = [];
    $eligible = 0;
    $active = 0;
    foreach ($subscriptions as $subscription) {
        $rule = json_decode((string)$subscription['rule_json'], true);
        $valid = is_array($rule) && (bool)array_diff_key($rule, ['_weapp_consent' => true]);
        $consentState = 'missing_local_record';
        if (is_array($rule) && array_key_exists('_weapp_consent', $rule)) {
            $consentState = is_array($rule['_weapp_consent']) && CoreGoodsNoticeService::hasConsent($rule, $capability)
                ? 'matches_current_template' : 'invalid_or_different_template';
        }
        $isActive = (int)$subscription['status'] === 1;
        if ($isActive) $active++;
        if ($isActive && $valid && $consentState !== 'invalid_or_different_template') $eligible++;
        if (is_array($rule)) unset($rule['_weapp_consent']);
        $rows[] = [
            'subscription_id' => (int)$subscription['subscription_id'],
            'name' => (string)($subscription['subscription_name'] ?? ''),
            'status' => (int)$subscription['status'],
            'rule' => is_array($rule) ? $rule : null,
            'local_authorization' => $consentState,
        ];
    }

    $blocked = [];
    if (empty($capability['enabled'])) $blocked[] = $capability['reason'] ?: '小程序通知配置不可用';
    if (!$member) $blocked[] = '会员不属于指定站点或不存在';
    elseif (empty($member['weapp_openid'])) $blocked[] = '该会员没有本站小程序 OpenID';
    if (!$eligible) $blocked[] = '没有有效订阅，或已记录的授权与当前模板不匹配；不恢复已取消的订阅';

    $template = (new CoreNoticeService())->getInfo($siteId, CoreGoodsNoticeService::KEY);
    $payload = CoreGoodsNoticeService::templateData((array)($template['weapp']['content'] ?? []), testVariables());
    if ($arrivalTemplate) $payload = CoreGoodsNoticeService::templateData(arrivalTemplateContent(), testVariables());
    if (!$payload) $blocked[] = '通知模板内容为空';

    return [
        'probe_version' => '1.2.0',
        'mapping_mode' => $arrivalTemplate ? 'arrival_template_test_only' : 'configured',
        'configuration_changed' => false,
        'site_id' => $siteId,
        'member_id' => $memberId,
        'member_found' => (bool)$member,
        'has_weapp_openid' => !empty($member['weapp_openid']),
        'configuration_enabled' => !empty($capability['enabled']),
        'app_id_masked' => masked((string)($capability['app_id'] ?? '')),
        'template_id_masked' => masked((string)($capability['template_id'] ?? '')),
        'subscription_count' => count($subscriptions),
        'active_subscription_count' => $active,
        'eligible_subscription_count' => $eligible,
        'subscriptions' => $rows,
        'message_preview' => $payload,
        'ready' => !$blocked,
        'blocked_reasons' => $blocked,
    ];
}

function testVariables(): array
{
    return [
        'goods_name' => '商城上新提醒测试',
        'goods_price' => '0.00',
        'change_type_name' => '通知测试',
        'change_summary' => '仅测试通知通道，不代表商品报价',
        'subscription_name' => '上新提醒测试',
        'match_time' => date('Y-m-d H:i:s'),
        'goods_count' => '0',
        'listing_time' => date('Y-m-d H:i'),
        'brand_name' => '测试品牌',
        'notice_remark' => '通知通道测试，不代表实际上新',
        'supplier_name' => '测试供应商',
    ];
}

function arrivalTemplateContent(): array
{
    // Explicit test fixture for the verified 51261 schema, not a persisted configuration override.
    return [
        ['上架商品数', '{goods_count}', 'number1'],
        ['上架时间', '{listing_time}', 'time2'],
        ['品牌', '{brand_name}', 'thing3'],
        ['备注', '{notice_remark}', 'thing4'],
        ['供应商', '{supplier_name}', 'thing5'],
    ];
}

function masked(string $value): string
{
    if ($value === '') return '';
    return strlen($value) > 10 ? substr($value, 0, 4) . '***' . substr($value, -4) : '***';
}

function wechatClient(int $siteId): object
{
    $app = clone CoreWeappService::app($siteId);
    $config = $app->getConfig()->all();
    $config['http']['retry'] = false;
    $app->setConfig(new Config($config));
    return $app->createClient();
}

function responseDetails(array $response): array
{
    // Allowlist diagnostics: never print raw errmsg, which can echo OpenID or token URLs.
    $details = ['wechat_errcode' => is_numeric($response['errcode'] ?? null) ? (int)$response['errcode'] : null];
    $message = is_string($response['errmsg'] ?? null) ? $response['errmsg'] : '';
    if (preg_match('/\bdata\.([a-z][a-z0-9_]{0,63})\.value\b/i', $message, $matches)) {
        $details['invalid_field'] = 'data.' . $matches[1] . '.value';
    }
    if (preg_match('/\brid:\s*([a-z0-9_-]{1,128})\b/i', $message, $matches)) {
        $details['wechat_request_id'] = $matches[1];
    }
    return $details;
}

function inspectTemplate(int $siteId, string $templateId, array $payload): array
{
    try {
        $response = wechatClient($siteId)->get('wxaapi/newtmpl/gettemplate', [
            'query' => [], 'timeout' => 5.0, 'max_duration' => 10.0,
        ])->toArray();
    } catch (\Throwable $e) {
        return ['status' => 'unavailable', 'message' => '读取微信模板失败，未发送消息；不输出可能含凭据的异常内容。'];
    }
    if (!isset($response['errcode']) || !is_numeric($response['errcode']) || (int)$response['errcode'] !== 0 || !is_array($response['data'] ?? null)) {
        return ['status' => 'unavailable', 'message' => '微信未返回有效模板列表，未发送消息。'] + responseDetails($response);
    }
    foreach ($response['data'] as $template) {
        if (!is_array($template) || ($template['priTmplId'] ?? '') !== $templateId) continue;
        $content = (string)($template['content'] ?? '');
        preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\.DATA\s*\}\}/i', $content, $matches);
        $expected = array_values(array_unique($matches[1]));
        preg_match_all('/^\s*([^:：{}\r\n]+?)\s*[:：]\s*\{\{\s*([a-z][a-z0-9_]*)\.DATA\s*\}\}/mu', $content, $labelMatches, PREG_SET_ORDER);
        $labels = [];
        foreach ($labelMatches as $label) $labels[$label[2]] = trim($label[1]);
        $configured = array_keys($payload);
        $missing = array_values(array_diff($expected, $configured));
        $unexpected = array_values(array_diff($configured, $expected));
        $enums = [];
        $invalidEnums = [];
        foreach ((array)($template['keywordEnumValueList'] ?? []) as $enum) {
            if (!is_array($enum)) continue;
            $key = (string)($enum['keywordCode'] ?? '');
            $allowed = $enum['enumValueList'] ?? [];
            if (!in_array($key, $expected, true) || !is_array($allowed) || !$allowed) continue;
            $enums[$key] = $allowed;
            if (isset($payload[$key]) && !in_array((string)$payload[$key]['value'], $allowed, true)) {
                $invalidEnums[] = ['field' => $key, 'sent_value' => $payload[$key]['value'], 'allowed_values' => $allowed];
            }
        }
        return [
            'status' => !$expected ? 'unrecognized_template' : ($missing || $unexpected ? 'fields_mismatch' : ($invalidEnums ? 'enum_mismatch' : 'matched')),
            'template_title' => (string)($template['title'] ?? ''),
            'wechat_template_content' => $content,
            'configured_fields' => $configured,
            'wechat_fields' => $expected,
            'wechat_field_labels' => $labels,
            'missing_fields' => $missing,
            'unexpected_fields' => $unexpected,
            'enum_rules' => $enums,
            'invalid_enums' => $invalidEnums,
            'message' => '仅核对字段及微信返回的枚举限制，不代表字段值全部合法或授权额度充足；不自动改模板或字段。',
        ];
    }
    return ['status' => 'template_missing', 'message' => '当前小程序的微信模板列表中没有找到已配置的模板。'];
}

function sendTest(int $siteId, int $memberId, array $capability, array $payload): array
{
    $openid = (string)(new Member())->where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])->value('weapp_openid');
    if ($openid === '') return ['status' => CoreGoodsNoticeService::SKIPPED, 'reason' => '客户没有本站小程序 OpenID'];
    try {
        // Keep this single-file probe usable before the production business service is updated.
        // Reuse the framework client/token and payload builder; capture the response before it is simplified.
        $response = wechatClient($siteId)->postJson('cgi-bin/message/subscribe/send', [
            'timeout' => 5.0, 'max_duration' => 10.0,
            'template_id' => $capability['template_id'], 'touser' => $openid,
            'page' => 'addon/phone_shop/pages/goods/list', 'data' => $payload,
        ])->toArray();
        return array_merge(CoreGoodsNoticeService::fromResponse($response), responseDetails($response));
    } catch (\Throwable $e) {
        return ['status' => CoreGoodsNoticeService::UNKNOWN, 'reason' => '微信请求结果未确认，为避免重复通知不自动重发；不输出可能含凭据的异常内容'];
    }
}

function run(int $siteId, int $memberId, bool $send, string $receiptDirectory, bool $checkTemplate = false, bool $arrivalTemplate = false): array
{
    $report = inspect($siteId, $memberId, $arrivalTemplate);
    $capability = (new CoreGoodsNoticeService())->capability($siteId);
    if (($checkTemplate || $arrivalTemplate) && $report['configuration_enabled']) {
        $report['template_check'] = inspectTemplate($siteId, (string)$capability['template_id'], $report['message_preview']);
        if ($arrivalTemplate && $report['template_check']['status'] === 'matched') {
            $sameMeaning = $report['template_check']['template_title'] === '新商品上架提醒';
            foreach (arrivalTemplateContent() as [$label, $variable, $key]) {
                $sameMeaning = $sameMeaning && ($report['template_check']['wechat_field_labels'][$key] ?? '') === $label;
            }
            if (!$sameMeaning) {
                $report['template_check']['status'] = 'template_meaning_mismatch';
                $report['template_check']['message'] = '字段编号相同，但模板标题或字段含义不同，不能套用本次上新模板试发。';
            }
        }
        if ($report['template_check']['status'] !== 'matched') {
            $report['ready'] = false;
            $report['blocked_reasons'][] = '微信模板核对未通过，请查看 template_check；未改配置，未发送通知';
        }
    }
    $report['mode'] = $send ? 'send_one_test_message' : 'check_only';
    if (!$send || !$report['ready']) {
        $report['outcome'] = !$report['ready'] ? 'blocked' : 'check_complete';
        $report['message'] = !$report['ready'] ? '未发送，请先查看 blocked_reasons。' : '检查完成，未发送。加 --send 才向此会员试发一条测试通知。';
        return $report;
    }

    $key = hash('sha256', 'phone_shop_notice_probe_v1:' . $siteId . ':' . $memberId . ':' . $capability['app_id'] . ':' . $capability['template_id']);
    if (!is_dir($receiptDirectory) && !@mkdir($receiptDirectory, 0700, true) && !is_dir($receiptDirectory)) {
        throw new \RuntimeException('Cannot create receipt directory');
    }
    $receiptFile = rtrim($receiptDirectory, '/\\') . '/' . $key . '.json';
    $handle = @fopen($receiptFile, 'c+');
    if ($handle === false) throw new \RuntimeException('Cannot open receipt file');
    try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            $report['outcome'] = 'already_running';
            $report['message'] = '同一会员的试发正在执行，本次不重复发送。';
            return $report;
        }
        $raw = stream_get_contents($handle);
        $lines = $raw !== false && trim($raw) !== '' ? explode("\n", trim($raw)) : [];
        $previous = $lines ? json_decode(end($lines), true) : null;
        if ($raw === false || (trim($raw) !== '' && (!is_array($previous) || !isset($previous['status'])))) {
            $report['outcome'] = 'receipt_unreadable';
            $report['message'] = '上次回执无法确认，本次不发送；请保留回执文件排查。';
            return $report;
        }
        if ($previous && !in_array((int)$previous['status'], [CoreGoodsNoticeService::FAILED, CoreGoodsNoticeService::SKIPPED], true)) {
            $report['outcome'] = 'duplicate_blocked';
            $report['previous_result'] = $previous;
            $report['message'] = '之前已受理或结果不明，本次未重复发送。请勿删除回执文件后盲目重试。';
            return $report;
        }

        // Persist before making the external call: a killed process must not cause a replay.
        saveReceipt($handle, ['status' => 0, 'reason' => '发送已开始，微信结果尚未确认', 'time' => date(DATE_ATOM)]);
        $result = sendTest($siteId, $memberId, $capability, $report['message_preview']);
        $result['time'] = date(DATE_ATOM);
        saveReceipt($handle, $result);
        $report['outcome'] = [
            CoreGoodsNoticeService::ACCEPTED => 'wechat_accepted',
            CoreGoodsNoticeService::FAILED => 'wechat_rejected',
            CoreGoodsNoticeService::UNKNOWN => 'result_unknown',
            CoreGoodsNoticeService::SKIPPED => 'not_sent',
        ][$result['status']] ?? 'result_unknown';
        $report['send_result'] = $result;
        $report['message'] = '本次只处理指定会员，不走队列，不改订阅状态。微信受理不代表已阅读；将此输出反馈即可。';
        return $report;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function saveReceipt($handle, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    // Append rather than truncate: an interrupted result write must retain the pending marker.
    if (fseek($handle, 0, SEEK_END) !== 0 || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
        throw new \RuntimeException('Cannot persist receipt');
    }
}

function main(): int
{
    $options = getopt('', ['site-id:', 'member-id:', 'send', 'check-template', 'arrival-template', 'help']);
    if (isset($options['help'])) {
        echo "将脚本放在包含 think、vendor 和 .env 的后端根目录，在宝塔终端执行：\n"
            . "php phone_shop_notice_probe.php --site-id=100005 --member-id=75\n"
            . "php phone_shop_notice_probe.php --site-id=100005 --member-id=75 --check-template\n"
            . "php phone_shop_notice_probe.php --site-id=100005 --member-id=75 --arrival-template --send\n"
            . "php phone_shop_notice_probe.php --site-id=100005 --member-id=75 --send\n"
            . "默认仅检查；--send 仅试发一条测试通知，数量 0、价格 0.00 均为测试占位，不代表实际上新或报价。\n"
            . "--check-template 只读微信模板并对照字段；可与 --send 合用，核对未通过则不发送。\n"
            . "--arrival-template 使用已核实的上新字段试发，强制核对微信模板标题和字段含义；不修改本站配置。\n"
            . "已取消的订阅不恢复。已受理或结果不明不重发；明确失败后可在修复原因后重新执行。\n";
        return 0;
    }
    $siteId = filter_var($options['site-id'] ?? '100005', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $memberId = filter_var($options['member-id'] ?? '75', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($siteId === false || $memberId === false) {
        fwrite(STDERR, "site-id 和 member-id 必须是正整数。\n");
        return 1;
    }
    try {
        $root = '';
        foreach ([__DIR__, dirname(__DIR__, 3)] as $candidate) {
            if (is_file($candidate . '/vendor/autoload.php') && is_file($candidate . '/think')) { $root = $candidate; break; }
        }
        if ($root === '') {
            fwrite(STDERR, "未找到框架。请将本文件放到含 think、vendor 和 .env 的后端目录，再执行。\n");
            return 1;
        }
        require_once $root . '/vendor/autoload.php';
        $app = new \think\App($root . DIRECTORY_SEPARATOR);
        $app->initialize();
        $app->request->siteId($siteId);
        $app->request->memberId($memberId);
        $report = run($siteId, $memberId, isset($options['send']), $app->getRuntimePath() . 'phone_shop_notice_probe', isset($options['check-template']), isset($options['arrival-template']));
        $report['php_version'] = PHP_VERSION;
        $report['checked_at'] = date(DATE_ATOM);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        return in_array($report['outcome'], ['check_complete', 'wechat_accepted'], true) ? 0 : 1;
    } catch (\Throwable $e) {
        // Exception messages/traces may contain SQL credentials or access_token URLs.
        echo json_encode([
            'outcome' => 'script_error', 'exception' => get_class($e), 'code' => $e->getCode(),
            'file' => basename($e->getFile()), 'line' => $e->getLine(),
            'message' => '执行异常，未自动重试；请反馈此输出，不要提供密钥或完整 OpenID。',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return 1;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) exit(main());
