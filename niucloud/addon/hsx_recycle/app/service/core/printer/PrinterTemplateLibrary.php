<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\service\core\printer;

use addon\hsx_recycle\app\model\printer\RecyclePrinterTemplate;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

/** 公共打印版式只读共享，打印机与业务触发规则始终由本站决定。 */
final class PrinterTemplateLibrary
{
    public const DEFAULT_KEY = 'HSX_RECYCLE_PRINT_DEFAULTS';

    public static function query(int $siteId)
    {
        if ($siteId < 0) throw new CommonException('站点信息不正确');
        return RecyclePrinterTemplate::where(function ($query) use ($siteId) {
            $query->where('site_id', $siteId);
            if ($siteId > 0) $query->whereOr(function ($public) {
                $public->where('site_id', 0)->where('status', 1);
            });
        });
    }

    public static function describe(array $row, int $siteId): array
    {
        if (!$row) return [];
        $public = (int)$row['site_id'] === 0;
        $row['is_public'] = $public;
        $row['can_edit'] = (int)$row['site_id'] === $siteId;
        $row['source_name'] = $public ? '公共模板' : '本站模板';
        if ($public) {
            $row['printer_id'] = 0;
            $row['trigger_event'] = '';
        }
        return $row;
    }

    public static function get(int $siteId, int $id): array
    {
        return self::describe(self::query($siteId)->where('template_id', $id)->findOrEmpty()->toArray(), $siteId);
    }

    public static function defaults(int $siteId): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, self::DEFAULT_KEY);
        return is_array($value) ? $value : [];
    }

    public static function selectDefault(int $siteId, string $type, int $id): void
    {
        $config = self::defaults($siteId);
        if ($id > 0) $config[$type] = $id;
        else unset($config[$type]);
        (new CoreConfigService())->setConfig($siteId, self::DEFAULT_KEY, $config);
    }

    public static function defaultTemplate(int $siteId, string $type): array
    {
        $preferred = (int)(self::defaults($siteId)[$type] ?? 0);
        if ($preferred > 0) {
            // 已明确选择但被停用时不悄悄换版式；打印计划会提示重新配置。
            return self::describe(self::query($siteId)->where('template_id', $preferred)
                ->where('template_type', $type)->where('status', 1)->findOrEmpty()->toArray(), $siteId);
        }
        return self::describe(self::query($siteId)->where('template_type', $type)->where('status', 1)
            ->where('is_default', 1)->order('site_id desc, template_id asc')->findOrEmpty()->toArray(), $siteId);
    }
}
