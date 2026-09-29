<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use app\service\core\notice\CoreNoticeService;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** 预约通知复用 sys_notice 的开关/模板 ID，字段映射存既有 sys_config。 */
class PickupNoticeConfigService
{
    public const NOTICE_KEY = 'hsx_recycle_pickup_update';
    public const CONFIG_KEY = 'HSX_RECYCLE_PICKUP_NOTICE';
    public const VARIABLES = [
        'order_no' => '回收订单编号', 'state_name' => '预约状态', 'carrier_name' => '快递公司',
        'pickup_time' => '预约时间', 'courier_name' => '快递员', 'courier_phone' => '快递员电话',
        'tracking_no' => '运单号', 'message' => '温馨提示',
    ];

    private static int $contextSiteId = 0;

    public function get(int $siteId): array
    {
        return self::withSite($siteId, function () use ($siteId) {
            $mapping = $this->mapping($siteId);
            $notice = (new CoreNoticeService())->getInfo($siteId, self::NOTICE_KEY);
            $result = ['notice_key' => self::NOTICE_KEY, 'variables' => self::VARIABLES];
            foreach (['weapp', 'wechat'] as $channel) {
                $result[$channel] = [
                    'enabled' => (int)!empty($notice['is_' . $channel]),
                    'template_id' => (string)($notice[$channel . '_template_id'] ?? ''),
                    'content' => $mapping[$channel]['content'] ?? [],
                ];
            }
            return $result;
        });
    }

    public function save(int $siteId, array $data): void
    {
        if ($siteId <= 0) throw new CommonException('站点无效');
        $config = $this->normalize($data);
        self::withSite($siteId, function () use ($siteId, $config) {
            Db::transaction(function () use ($siteId, $config) {
                (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $config);
                $notice = ['is_sms' => 0];
                foreach (['weapp', 'wechat'] as $channel) {
                    $notice['is_' . $channel] = $config[$channel]['enabled'];
                    $notice[$channel . '_template_id'] = $config[$channel]['template_id'];
                }
                (new CoreNoticeService())->edit($siteId, self::NOTICE_KEY, $notice);
            });
        });
    }

    public function normalize(array $data): array
    {
        $result = [];
        foreach (['weapp', 'wechat'] as $channel) {
            $input = $data[$channel] ?? [];
            if (!is_array($input)) throw new CommonException('通知渠道配置格式错误');
            $enabled = (int)!empty($input['enabled']);
            $id = trim((string)($input['template_id'] ?? ''));
            if (strlen($id) > 100 || ($id !== '' && !preg_match('/^[A-Za-z0-9_-]+$/D', $id))) {
                throw new CommonException('微信模板 ID 格式错误');
            }
            $rows = $input['content'] ?? [];
            if (!is_array($rows) || count($rows) > 20) throw new CommonException('通知字段映射格式错误或超过 20 项');
            $content = [];
            $seen = [];
            foreach ($rows as $row) {
                if (!is_array($row)) throw new CommonException('通知字段映射格式错误');
                $variable = trim((string)($row['variable'] ?? ''));
                $keyword = trim((string)($row['keyword'] ?? ''));
                if (!isset(self::VARIABLES[$variable])) throw new CommonException('不支持的预约通知变量：' . $variable);
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,49}$/D', $keyword) || isset($seen[$keyword])) {
                    throw new CommonException('模板字段编码无效或重复：' . $keyword);
                }
                $seen[$keyword] = true;
                $content[] = [
                    'label' => mb_substr(trim((string)($row['label'] ?? self::VARIABLES[$variable])), 0, 30),
                    'variable' => $variable, 'keyword' => $keyword,
                ];
            }
            if ($enabled && ($id === '' || !$content)) throw new CommonException('启用通知前请配置真实模板 ID 和字段映射');
            $result[$channel] = ['enabled' => $enabled, 'template_id' => $id, 'content' => $content];
        }
        return $result;
    }

    public function mapping(int $siteId): array
    {
        if ($siteId <= 0) return [];
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        return is_array($value) ? $value : [];
    }

    /** 使用框架现有动态字典扩展点；回调无 site_id 请求头时不能读错站点。 */
    public static function templateContent(string $channel, array $context = []): array
    {
        $siteId = self::$contextSiteId ?: (int)($context['site_id'] ?? 0);
        $mapping = (new self())->mapping($siteId);
        return array_map(static fn(array $row): array => [
            $row['label'], '{' . $row['variable'] . '}', $row['keyword'],
        ], $mapping[$channel]['content'] ?? []);
    }

    public static function withSite(int $siteId, callable $callback)
    {
        $previous = self::$contextSiteId;
        self::$contextSiteId = $siteId;
        try { return $callback(); }
        finally { self::$contextSiteId = $previous; }
    }
}
