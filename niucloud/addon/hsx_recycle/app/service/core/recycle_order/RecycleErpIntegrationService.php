<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use app\model\site\Site;
use app\model\sys\SysConfig;
use app\service\core\site\CoreSiteService;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Cache;
use think\facade\Db;

/** 站点级联动配置；历史按设备创建时间生效，不迁移任何旧设备的付款责任。 */
class RecycleErpIntegrationService
{
    public const CONFIG_KEY = 'recycle_erp_integration';

    /** @return array{mode:string,installed:bool,configured:bool,message:string,changed_at:int} */
    public function get(int $siteId): array
    {
        return $this->describe($this->history($siteId));
    }

    /** 查询失败必须上抛，不能把“无法确认”当作未安装并放开本地付款。 */
    public function isInstalled(int $siteId): bool
    {
        $this->assertSiteId($siteId);
        return in_array('hsx_erp', (new CoreSiteService())->getAddonKeysBySiteId($siteId), true);
    }

    /**
     * 返回完整责任时间线。未配置时冻结前的兼容模式仅由当前安装状态推导。
     * @return array{mode:string,initial_mode:string,history:array,changed_at:int,configured:bool,installed:bool}
     */
    public function history(int $siteId): array
    {
        return $this->readHistory($siteId, false);
    }

    private function readHistory(int $siteId, bool $lock): array
    {
        $this->assertSiteId($siteId);
        $installed = $this->isInstalled($siteId);
        $raw = $this->configService($lock)->getConfigValue($siteId, self::CONFIG_KEY);
        if ($raw === []) {
            $mode = $installed ? 'self_erp' : 'local';
            return ['mode' => $mode, 'initial_mode' => $mode, 'history' => [], 'changed_at' => 0, 'configured' => false, 'installed' => $installed];
        }
        $this->validateStoredConfig($raw);
        return array_merge($raw, ['configured' => true, 'installed' => $installed]);
    }

    /** 保存只追加切换历史；反复保存相同模式不新增切换记录。 */
    public function save(int $siteId, string $mode, bool $confirm): array
    {
        $this->assertSiteId($siteId);
        if (!$confirm) throw new CommonException('请确认切换仅影响生效后创建的新设备，旧设备仍按原责任处理');
        if (!in_array($mode, ['local', 'self_erp'], true)) throw new CommonException('不支持的 ERP 联动模式');

        $saved = Db::transaction(function () use ($siteId, $mode): array {
            // 锁已存在的站点行，首次配置也有稳定锁对象，不能只锁尚不存在的配置行。
            $site = Site::where([['site_id', '=', $siteId]])->field('site_id')->lock(true)->findOrEmpty();
            if ($site->isEmpty()) throw new CommonException('站点不存在');
            $current = $this->readHistory($siteId, true);
            if ($mode === 'self_erp' && !$current['installed']) throw new CommonException('请先为当前站点安装 ERP，再开启联动');
            if ($current['configured'] && $current['mode'] === $mode) return $current;

            $history = $current['history'];
            $changedAt = $this->now();
            if ($mode !== $current['mode']) {
                $lastAt = $history === [] ? 0 : (int)$history[array_key_last($history)]['at'];
                // create_at 只有秒精度：从下一秒生效，保护本次保存前同秒创建的设备。
                $changedAt = max($changedAt + 1, $lastAt + 1);
                $history[] = ['at' => $changedAt, 'mode' => $mode];
            }
            $value = ['mode' => $mode, 'initial_mode' => $current['initial_mode'], 'history' => $history, 'changed_at' => $changedAt];
            if (!$this->configService(true)->setConfig($siteId, self::CONFIG_KEY, $value)) {
                throw new CommonException('ERP 联动配置保存失败');
            }
            return array_merge($value, ['configured' => true, 'installed' => $current['installed']]);
        });
        // CoreConfig 在事务内也会清缓存；提交后再清一次，防止并发旧缓存回填。
        Cache::tag(CoreConfigService::$cache_tag_name . $siteId)->clear();
        return $this->describe($saved);
    }

    /** 配置读和 setConfig 内部的存在性判断都绕过缓存，确保锁内读取上一笔已提交历史。 */
    protected function configService(bool $lock = false): CoreConfigService
    {
        return new class($lock) extends CoreConfigService {
            public function __construct(private bool $lock)
            {
                parent::__construct();
            }

            public function getConfig(int $site_id, string $key): array
            {
                // 保存时使用当前读，外层事务已建立旧快照时也不能丢失刚提交的历史。
                return SysConfig::where([['site_id', '=', $site_id], ['config_key', '=', $key]])
                    ->field('id,site_id,config_key,value,status,create_time,update_time')->lock($this->lock)->findOrEmpty()->toArray();
            }
        };
    }

    protected function now(): int
    {
        return time();
    }

    private function describe(array $config): array
    {
        $message = !$config['configured']
            ? '尚未单独配置，暂按当前安装状态兼容运行；保存后仅影响生效后创建的新设备'
            : ($config['mode'] === 'self_erp'
                ? ($config['installed'] ? '新设备由 ERP 接管，旧设备继续按原责任处理' : '已配置 ERP 接管但当前未安装，请恢复 ERP；旧账款不会改由本地付款')
                : '新设备在回收系统独立处理，旧 ERP 设备和账款继续由 ERP 负责');
        if ($config['configured'] && (int)$config['changed_at'] > 0) {
            $message .= '；' . ((int)$config['changed_at'] > $this->now() ? '预计生效时间：' : '最近生效时间：')
                . date('Y-m-d H:i:s', (int)$config['changed_at']) . '，仅适用于该时间起新建的设备';
        }
        return ['mode' => $config['mode'], 'installed' => $config['installed'], 'configured' => $config['configured'],
            'message' => $message, 'changed_at' => (int)$config['changed_at']];
    }

    private function assertSiteId(int $siteId): void
    {
        if ($siteId <= 0) throw new CommonException('无法确认当前站点，已暂停联动配置操作');
    }

    private function validateStoredConfig(mixed $config): void
    {
        if (!is_array($config) || !in_array($config['mode'] ?? '', ['local', 'self_erp'], true)
            || !in_array($config['initial_mode'] ?? '', ['local', 'self_erp'], true)
            || !is_array($config['history'] ?? null) || !is_int($config['changed_at'] ?? null)) {
            throw new CommonException('ERP 联动责任配置不完整，请检查配置后再操作');
        }
        $lastAt = 0;
        $lastMode = $config['initial_mode'];
        foreach ($config['history'] as $entry) {
            if (!is_array($entry) || !is_int($entry['at'] ?? null) || $entry['at'] <= $lastAt
                || !in_array($entry['mode'] ?? '', ['local', 'self_erp'], true)) {
                throw new CommonException('ERP 联动责任历史存在歧义，请检查配置后再操作');
            }
            $lastAt = $entry['at'];
            $lastMode = $entry['mode'];
        }
        if ($lastMode !== $config['mode']) throw new CommonException('ERP 联动模式与责任历史不一致，请检查配置后再操作');
    }
}
