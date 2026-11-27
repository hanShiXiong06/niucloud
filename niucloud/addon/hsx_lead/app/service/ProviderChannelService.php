<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\provider\{ProviderRegistry,ProviderException};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\{Db,Cache};
use app\service\core\sys\CoreConfigService;

/** Site policy, independent of adapter registration and merchants' own sync switches. */
final class ProviderChannelService
{
    public const KEY = 'HSX_LEAD_PROVIDER_CHANNELS';
    public const MESSAGE = '关闭后停止该渠道的新授权与手动、自动采集，保留账号、任务和历史库存；重新开启仅按商户原同步开关恢复。';
    private const DEFAULT_NAMES = ['wanji' => 'W平台', 'soujike' => 'S平台', 'aiguanji' => 'A平台', 'jixiaoer' => 'J平台', 'jkb' => 'K平台'];
    public function __construct(private int $site)
    {
        if ($site <= 0) throw new CommonException('无效的渠道配置站点');
    }

    /** Display text only: never a provider identifier, login parameter or endpoint. */
    public static function displayNameValue($value): string
    {
        if (!is_string($value)
            || !mb_check_encoding($value, 'UTF-8')
            || preg_match('/[<>\p{C}]/u', $value)) throw new CommonException('渠道展示名称须为1—24字纯文本，不允许HTML或控制字符');
        $value = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value) ?? '';
        if ($value === '' || mb_strlen($value) > 24) throw new CommonException('请填写1—24字的渠道展示名称');
        return $value;
    }

    private static function effectiveName(string $canonical, array $config): string
    {
        return $config['display_names'][$canonical] ?? self::DEFAULT_NAMES[$canonical] ?? 'ERP平台';
    }

    public function displayName(string $key): string
    {
        $canonical = (new ProviderRegistry())->definition($key)->key();
        return self::effectiveName($canonical, $this->stored());
    }

    private function stored(): array
    {
        // Do not cache this policy in a long-running scheduler or a request object.
        $row = Db::name('sys_config')->where('site_id', $this->site)->where('config_key', self::KEY)->field('value')->find();
        if (!$row) return [
            'enabled' => array_fill_keys(array_column(ProviderRegistry::supported(), 'key'), true),
            'display_names' => [],
            'revision' => hash('sha256', self::KEY.':default'),
            'valid' => true
        ];
        $raw = (string) $row['value'];
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            $data = null;
        }
        $valid = is_array($data)
            && is_array($data['enabled'] ?? null)
            && is_string($data['revision'] ?? null)
            && preg_match('/^[a-f0-9]{32}$/D', $data['revision']);
        if ($valid) foreach ($data['enabled'] as $key => $value) if (!is_string($key) || !is_bool($value)) {
            $valid = false;
            break;
        }
        // Bad display text cannot change the established enable/disable policy or SQL scheduler gate.
        $names = [];
        if ($valid && is_array($data['display_names'] ?? null)) foreach (self::DEFAULT_NAMES as $key => $fallback) {
            if (!array_key_exists($key, $data['display_names'])) continue;
            try {
                $names[$key] = self::displayNameValue($data['display_names'][$key]);
            } catch (CommonException $ignored) {/* safe fixed alias */
            }
        }
        return [
            'enabled' => $valid ? $data['enabled'] : [],
            'display_names' => $names,
            'revision' => $valid ? $data['revision'] : hash('sha256', 'invalid:'.$raw),
            'valid' => (bool) $valid
        ];
    }

    public function enabled(string $key): bool
    {
        $canonical = (new ProviderRegistry())->definition($key)->key();
        $config = $this->stored();
        return ($config['enabled'][$canonical] ?? false) === true;
    }
    public function available(): array
    {
        $config = $this->stored();
        $items = [];
        foreach (ProviderRegistry::supported() as $item) if (($config['enabled'][$item['key']] ?? false) === true) {
            $item['name'] = self::effectiveName($item['key'], $config);
            $item['display_name'] = $item['name'];
            $items[] = $item;
        }
        return $items;
    }
    /** One fresh policy read for a bounded presentation batch; not a write gate cache. */
    public function summaries(array $keys): array
    {
        if (!$keys) return [];
        $config = $this->stored();
        $registry = new ProviderRegistry();
        $result = [];
        foreach (array_unique($keys) as $key) {
            $canonical = $registry->definition((string) $key)->key();
            $result[$canonical] = ['enabled' => ($config['enabled'][$canonical] ?? false) === true,
                'name' => self::effectiveName($canonical, $config)];
        }
        return $result;
    }
    /** Shared short lock for policy changes, token writes and generation publication. No HTTP under it. */
    public function lockSite(): void
    {
        if (!Db::name('site')->where('site_id', $this->site)->lock(true)->field('site_id')->find()) throw new CommonException('站点不存在');
    }
    public function assertEnabled(string $key, bool $lockSite = false): void
    {
        if ($lockSite) $this->lockSite();
        if (!$this->enabled($key)) throw new ProviderException('channel_disabled', '该ERP渠道已由本站管理员关闭，授权和采集已暂停；历史记录保留，可联系管理员或选择其他已开启渠道');
    }
    public function configuration(): array
    {
        $config = $this->stored();
        $items = [];
        foreach (ProviderRegistry::supported() as $definition) {
            $connections = Data::query('connection', $this->site)->where('provider', $definition['key']);
            $name = self::effectiveName($definition['key'], $config);
            $items[] = ['key' => $definition['key'], 'name' => $name, 'display_name' => $name, 'technical_name' => $definition['name'], 'enabled' => ($config['enabled'][$definition['key']] ?? false) === true,
                'connection_count' => (int) (clone $connections)->count(), 'automatic_sync_count' => (int) (clone $connections)->where('enabled', 1)->count(),
                'renewal' => $definition['renewal'] ?? [], 'notes' => $definition['notes'] ?? []];
        }
        return ['revision' => $config['revision'], 'items' => $items, 'policy_message' => ($config['valid'] ? '' : '配置异常，当前渠道全部暂停；请核对开关并保存修复。').self::MESSAGE];
    }
    public function save(Context $context, array $input): array
    {
        if ($context->site !== $this->site) throw new CommonException('渠道配置不属于当前站点');
        $context->permission('hsx_lead_cooperation_config');
        $context->permission('hsx_lead_cooperation_config_save');
        $revision = Data::text($input, 'revision', 64, true);
        $items = $input['items'] ?? null;
        $keys = array_column(ProviderRegistry::supported(), 'key');
        if (!is_array($items) || count($items) !== count($keys)) throw new CommonException('请完整提交当前ERP渠道列表');
        $enabled = [];
        $names = [];
        foreach ($items as $item) {
            if (!is_array($item)
                || !is_string($item['key'] ?? null)
                || !in_array($item['key'], $keys, true)
                || isset($enabled[$item['key']])
                || !is_bool($item['enabled'] ?? null)) throw new CommonException('ERP渠道开关格式错误，请重新读取');
            $enabled[$item['key']] = $item['enabled'];
            if (array_key_exists('display_name', $item)) $names[$item['key']] = self::displayNameValue($item['display_name']);
        }
        Db::transaction(function () use ($context, $revision, $enabled, $names): void {
            $this->lockSite();
            $old = $this->stored();
            if (!hash_equals($old['revision'], $revision)) throw new CommonException('ERP渠道配置已被其他管理员修改，请重新读取后再保存');
            $effective = [];
            foreach ($enabled as $key => $flag) $effective[$key] = $names[$key] ?? self::effectiveName($key, $old);
            $value = Data::json([
                    'enabled' => $enabled,
                    'display_names' => $effective,
                    'revision' => bin2hex(random_bytes(16)),
                    'update_at' => time(),
                    'operator_uid' => $context->uid
                ]);
            $query = Db::name('sys_config')->where('site_id', $this->site)->where('config_key', self::KEY);
            $existing = (clone $query)->lock(true)->field('id')->find();
            if ($existing) $query->update(['value' => $value, 'update_time' => time()]);
            else Db::name('sys_config')->insert([
                    'site_id' => $this->site,
                    'config_key' => self::KEY,
                    'value' => $value,
                    'status' => 1,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
            $context->audit('provider_channels', 0, 'saved', ['enabled' => $enabled, 'display_names' => $effective]);
        });
        Cache::tag(CoreConfigService::$cache_tag_name.$this->site)->clear();
        return $this->configuration();
    }

    /** Filter before LIMIT so paused connections cannot starve other sites/channels. */
    public static function filterEnabledConnections($query): void
    {
        $query->leftJoin('sys_config channel_policy', "channel_policy.site_id=c.site_id AND channel_policy.config_key='".self::KEY."'");
        $json = "IF(JSON_VALID(channel_policy.value),channel_policy.value,'{}')";
        $path = "CONCAT('$.enabled.',JSON_QUOTE(c.provider))";
        $query->whereRaw("(channel_policy.id IS NULL OR (JSON_VALID(channel_policy.value) AND JSON_TYPE(JSON_EXTRACT($json,'$.enabled'))='OBJECT' AND JSON_UNQUOTE(JSON_EXTRACT($json,'$.revision')) REGEXP '^[a-f0-9]{32}$' AND JSON_TYPE(JSON_EXTRACT($json,$path))='BOOLEAN' AND JSON_UNQUOTE(JSON_EXTRACT($json,$path))='true'))");
        $query->whereIn('c.provider', array_column(ProviderRegistry::supported(), 'key'));
    }
}
