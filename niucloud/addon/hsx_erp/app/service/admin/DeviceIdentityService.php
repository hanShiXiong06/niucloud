<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

/**
 * 设备身份(名称 + 小标题 + 串号)统一取数 / 回填助手。
 *
 * 单一口径,供财务 / 资产 / 出入库 / 追踪等各处复用:
 *   - 名称  = 回收设备型号(model)
 *   - 小标题 = 质检模板里"加入描述"的 ≤5 个字段值(label 拼接,如 256GB · 黑色)
 *   - 串号  = imei
 *   - identity_text = 三者拼成的一整行文字,前端可直接展示 / hover 展开
 *
 * 数据源是回收插件的 RecycleDeviceService::deviceIdentityMap();回收不在或无质检时,
 * 由 ERP 资产侧(型号/容量/颜色/IMEI)兜底回退,保证任何场景都有可读文字。
 */
class DeviceIdentityService
{
    private const RECYCLE_SVC = '\addon\hsx_recycle\app\service\admin\order\RecycleDeviceService';

    /**
     * 批量取设备身份映射。
     * @param int   $siteId
     * @param array $deviceIds 回收设备ID集合
     * @return array<int,array> deviceId => identity(可能为空数组,表示回收侧无数据)
     */
    public static function map(int $siteId, array $deviceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $deviceIds), static fn ($v) => $v > 0)));
        if (empty($ids) || !class_exists(self::RECYCLE_SVC)) {
            return [];
        }
        try {
            $cls = self::RECYCLE_SVC;
            $svc = new $cls($siteId);
            return $svc->deviceIdentityMap($ids);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 把设备身份写到一行数据上(就地)。
     * 约定:调用前该行已设置 device_model / device_imei / device_capacity / device_color(ERP 侧),
     * 本方法据此在回收身份缺失时回退,确保 device_identity_text 永远有值。
     * @param array      $row      列表/详情的一行(引用)
     * @param array|null $identity RecycleDeviceService 身份;null/空表示回收无数据
     */
    public static function attachToRow(array &$row, ?array $identity): void
    {
        $name = trim((string)($identity['name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['device_model'] ?? ''));
        }
        $imei = trim((string)($identity['imei'] ?? ''));
        if ($imei === '') {
            $imei = trim((string)($row['device_imei'] ?? ''));
        }
        $subtitle = trim((string)($identity['subtitle'] ?? ''));
        $summaryFields = is_array($identity['summary_fields'] ?? null) ? $identity['summary_fields'] : [];

        // 回收无小标题 → 用 ERP 容量/颜色兜底拼一个
        if ($subtitle === '') {
            $subtitle = trim(implode(' · ', array_filter([
                trim((string)($row['device_capacity'] ?? '')),
                trim((string)($row['device_color'] ?? '')),
            ], static fn ($v) => $v !== '')));
        }

        $identityText = trim((string)($identity['identity_text'] ?? ''));
        if ($identityText === '') {
            $parts = array_filter([
                $name,
                $subtitle,
                $imei !== '' ? ('IMEI ' . $imei) : '',
            ], static fn ($v) => $v !== '');
            $identityText = implode('  ｜  ', $parts);
        }

        $row['device_name'] = $name;
        $row['device_subtitle'] = $subtitle;
        $row['device_summary_fields'] = $summaryFields;
        $row['device_identity_text'] = $identityText;
    }
}
