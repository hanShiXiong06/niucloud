<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use app\service\core\sys\CoreConfigService;

/**
 * ERP / 财务 站点级配置(复用平台 CoreConfigService,配置 key = HSX_ERP)
 *
 *  - allow_instant_settle : 现结(收款)开关。开启=非财务可当场清账(收款);
 *                           关闭=下单只生成应收/应付,清账由财务完成(销售可加备注做参考)。
 *
 * 说明:不做"ERP 总开关"——上线节奏由"测试环境 + 分阶段部署"解决,不在运行时切换。
 */
class ErpConfigService
{
    const CONFIG_KEY = 'HSX_ERP';

    public static function defaults(): array
    {
        return [
            'allow_instant_settle' => 1, // 默认允许当场收款;财务接管后可关闭,改为只挂应收应付
        ];
    }

    public function getRaw(int $site_id): array
    {
        $info  = (new CoreConfigService())->getConfig($site_id, self::CONFIG_KEY);
        $value = $info['value'] ?? [];
        return array_merge(self::defaults(), is_array($value) ? $value : []);
    }

    /** 给前端用(目前与 raw 一致,无敏感字段) */
    public function get(int $site_id): array
    {
        return $this->getRaw($site_id);
    }

    public function save(int $site_id, array $data): bool
    {
        $old = $this->getRaw($site_id);
        $config = [
            'allow_instant_settle' => ((int)($data['allow_instant_settle'] ?? $old['allow_instant_settle'])) ? 1 : 0,
        ];
        (new CoreConfigService())->setConfig($site_id, self::CONFIG_KEY, $config);
        return true;
    }

    /** 门控便捷读取:是否允许当场收款(现结) */
    public static function instantSettleAllowed(int $site_id): bool
    {
        return (int)((new self())->getRaw($site_id)['allow_instant_settle']) === 1;
    }
}
