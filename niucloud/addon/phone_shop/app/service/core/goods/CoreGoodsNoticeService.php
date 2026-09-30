<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Brand;
use app\model\member\Member;
use app\model\site\Site;
use app\job\notice\Notice;
use app\service\core\notice\CoreNoticeService;
use app\service\core\weapp\CoreWeappConfigService;
use app\service\core\weapp\CoreWeappService;
use think\facade\Log;
use EasyWeChat\Kernel\Config;

/** 插件内核验微信回执；公共发送器不返回回执，不能据此判断发送成功。 */
class CoreGoodsNoticeService
{
    public const KEY = 'phone_shop_goods_match';
    public const ACCEPTED = 1;
    public const FAILED = 2;
    public const UNKNOWN = 3;
    public const SKIPPED = 4;

    public function capability(int $siteId): array
    {
        $template = (new CoreNoticeService())->getInfo($siteId, self::KEY);
        $app = (new CoreWeappConfigService())->getWeappConfig($siteId);
        $reason = '';
        if (empty($app['app_id'])) $reason = '尚未配置本站销售小程序';
        elseif (empty($app['is_authorization']) && empty($app['app_secret'])) $reason = '本站销售小程序密钥未配置完整';
        elseif (empty($template['is_weapp'])) $reason = '尚未开启“商品上新与调价订阅提醒”的小程序渠道';
        elseif (empty($template['weapp_template_id']) || empty($template['weapp']['content'])) $reason = '尚未配置商品订阅消息模板';
        return [
            'enabled' => $reason === '', 'reason' => $reason,
            'template_id' => $reason === '' ? (string)$template['weapp_template_id'] : '',
            'app_id' => (string)($app['app_id'] ?? ''),
            'tips' => '一次授权通常对应一条通知；微信受理不代表客户已阅读。',
        ];
    }

    /** 只记录用户本次明确选择，不把它当作微信剩余发送额度。 */
    public function consent(int $siteId, string $channel, array $authorization): array
    {
        if ($channel !== 'weapp' || ($authorization['status'] ?? '') !== 'accepted') return [];
        $capability = $this->capability($siteId);
        if (!$capability['enabled'] || ($authorization['template_id'] ?? '') !== $capability['template_id']) return [];
        return ['app_id' => $capability['app_id'], 'template_id' => $capability['template_id'], 'accepted_at' => time()];
    }

    public static function hasConsent(array $rule, array $capability): bool
    {
        $consent = $rule['_weapp_consent'] ?? [];
        return !empty($capability['enabled']) && !empty($consent['accepted_at'])
            && ($consent['app_id'] ?? '') === $capability['app_id']
            && ($consent['template_id'] ?? '') === $capability['template_id'];
    }

    /** 原有公众号/短信渠道继续使用框架，只是不再把“入队”记为微信发送成功。 */
    public function queueOtherChannels(int $siteId, int $matchId): void
    {
        $template = (new CoreNoticeService())->getInfo($siteId, self::KEY);
        if (empty($template['is_wechat']) && empty($template['is_sms'])) return;
        $template['is_weapp'] = 0;
        Notice::dispatch(['site_id' => $siteId, 'key' => self::KEY, 'data' => ['match_id' => $matchId], 'template' => $template], is_async: true);
    }

    /** 单商品和批次共用；只统计当前接收者匹配的本站设备，不公开上游站点信息。 */
    public static function arrivalVariables(int $siteId, array $goods, int $fallbackTime = 0): array
    {
        $goods = array_column(array_filter($goods, static fn($item) => (int)($item['site_id'] ?? 0) === $siteId && (int)($item['goods_id'] ?? 0) > 0), null, 'goods_id');
        $brandIds = array_values(array_unique(array_filter(array_map('intval', array_column($goods, 'brand_id')), static fn($id) => $id > 0)));
        $brands = $brandIds ? (new Brand())->where('site_id', $siteId)->whereIn('brand_id', $brandIds)->column('brand_name', 'brand_id') : [];
        $names = [];
        $listingTime = 0;
        foreach ($goods as $item) {
            $name = trim((string)($brands[(int)($item['brand_id'] ?? 0)] ?? ''));
            $names[] = $name !== '' ? $name : '品牌未标注';
            $created = $item['create_time'] ?? 0;
            $timestamp = is_numeric($created) ? (int)$created : (int)strtotime((string)$created);
            $listingTime = max($listingTime, $timestamp);
        }
        $supplier = trim((string)(new Site())->where('site_id', $siteId)->value('site_name'));
        return [
            'goods_count' => (string)count($goods),
            'listing_time' => date('Y-m-d H:i', $listingTime > 0 ? $listingTime : ($fallbackTime > 0 ? $fallbackTime : time())),
            'brand_name' => implode('、', array_unique($names)) ?: '品牌未标注',
            'supplier_name' => $supplier !== '' ? $supplier : '本站商城',
        ];
    }

    /** 返回微信明确受理/明确失败/结果待核实/未发送，禁止把超时当作可安全重发。 */
    public function send(int $siteId, int $memberId, array $vars, string $page): array
    {
        $capability = $this->capability($siteId);
        if (!$capability['enabled']) return $this->result(self::SKIPPED, $capability['reason']);
        $openid = (string)(new Member())->where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])->value('weapp_openid');
        if ($openid === '') return $this->result(self::SKIPPED, '客户未绑定本站销售小程序，请在小程序登录并重新订阅');
        $template = (new CoreNoticeService())->getInfo($siteId, self::KEY);
        $data = self::templateData((array)$template['weapp']['content'], $vars);
        if (!$data) return $this->result(self::SKIPPED, '商品通知模板字段为空');
        try {
            // 复用框架的应用/授权 token。只在此次克隆实例关闭传输层自动重试，
            // 防止网络结果不确定时 SDK 自己重复 POST；不影响其他业务的客户端。
            $app = clone CoreWeappService::app($siteId);
            $config = $app->getConfig()->all();
            $config['http']['retry'] = false;
            $app->setConfig(new Config($config));
            $response = $app->createClient()->postJson('cgi-bin/message/subscribe/send', [
                'timeout' => 5.0, 'max_duration' => 10.0,
                'template_id' => $capability['template_id'], 'touser' => $openid, 'page' => $page, 'data' => $data,
            ])->toArray();
            return self::fromResponse($response);
        } catch (\Throwable $e) {
            // 请求有可能已被微信接收，不自动重发，以免重复打扰/再次消耗授权。
            // 不写 access_token/openid 等敏感信息或含凭据的请求 URL。
            Log::error('[phone_shop 微信订阅请求异常] site=' . $siteId . ' member=' . $memberId . ' type=' . get_class($e) . ' code=' . $e->getCode());
            return $this->result(self::UNKNOWN, '微信请求结果未确认，请核查通知日志；为避免重复通知，不自动重发');
        }
    }

    public static function templateData(array $content, array $vars): array
    {
        $replace = [];
        foreach ($vars as $key => $value) $replace['{' . $key . '}'] = (string)$value;
        $data = [];
        foreach ($content as $field) {
            if (count($field) < 3) continue;
            $key = (string)$field[2];
            $value = strtr((string)$field[1], $replace);
            if (str_starts_with($key, 'thing')) $value = mb_substr($value, 0, 20);
            elseif (str_starts_with($key, 'phrase')) $value = mb_substr($value, 0, 5);
            $data[$key] = ['value' => $value];
        }
        return $data;
    }

    public static function fromResponse(array $response): array
    {
        if (!array_key_exists('errcode', $response) || !is_numeric($response['errcode'])) {
            return ['status' => self::UNKNOWN, 'reason' => '微信未返回明确结果，请核查；不自动重发'];
        }
        $code = (int)$response['errcode'];
        if ($code === 0) return ['status' => self::ACCEPTED, 'reason' => '微信已受理（不代表已阅读）'];
        $reasons = [
            43101 => '客户未授权、已取消或一次性订阅额度已用完，请客户再次订阅',
            40003 => '客户的小程序身份不匹配，请在本站小程序重新登录',
            40037 => '微信模板无效，请检查当前小程序的消息模板',
            47003 => '模板字段与微信要求不符，请检查模板及字段格式',
            45009 => '微信接口调用达到限制，请稍后重试',
            41030 => '消息跳转页面无效，请发布包含商品列表页的小程序版本',
            40001 => '小程序凭据无效，请检查配置',
            42001 => '微信凭据已过期，请刷新凭据后再重试',
        ];
        $result = ['status' => self::FAILED, 'reason' => '微信 ' . $code . '：' . ($reasons[$code] ?? '发送被拒绝，请根据错误码检查微信配置'), 'wechat_errcode' => $code];
        // 只保留可安全展示的字段路径和请求编号，不输出可能携带身份/凭据的完整回执。
        $message = is_string($response['errmsg'] ?? null) ? $response['errmsg'] : '';
        if (preg_match('/\bdata\.([a-z][a-z0-9_]{0,63})\.value\b/i', $message, $matches)) {
            $result['invalid_field'] = 'data.' . $matches[1] . '.value';
            $result['reason'] .= '（' . $result['invalid_field'] . '）';
        }
        if (preg_match('/\brid:\s*([a-z0-9_-]{1,128})\b/i', $message, $matches)) {
            $result['wechat_request_id'] = $matches[1];
        }
        return $result;
    }

    private function result(int $status, string $reason): array
    {
        return ['status' => $status, 'reason' => $reason];
    }
}
