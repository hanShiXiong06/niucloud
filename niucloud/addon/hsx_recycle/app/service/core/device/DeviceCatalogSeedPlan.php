<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device;

use core\exception\CommonException;

/** 初始化的纯数据规则，独立于数据库，便于验证关联和事务重试。 */
final class DeviceCatalogSeedPlan
{
    public const TABLES = [
        'recycle_device_model_dict', 'recycle_check_template', 'recycle_check_group',
        'recycle_check_field', 'recycle_check_option', 'recycle_template_binding',
        'recycle_check_dict', 'recycle_check_option_severity',
    ];

    public static function remapId(array $map, int $oldId, string $kind, bool $optional = false): int
    {
        if ($oldId === 0 && $optional) return 0;
        if ($oldId <= 0 || !isset($map[$oldId])) {
            throw new CommonException('初始化中止：' . $kind . '关联缺失，原记录ID=' . $oldId);
        }
        return (int)$map[$oldId];
    }

    public static function row(string $table, array $row, array $maps): array
    {
        if (!in_array($table, self::TABLES, true)) throw new CommonException('不允许复制此业务表');
        if ((int)($row['site_id'] ?? -1) !== DeviceCatalogPolicy::SEED_SITE_ID) {
            throw new CommonException('初始化来源必须是100005站');
        }
        unset($row['id']);
        $row['site_id'] = 0;
        if ($table === 'recycle_device_model_dict') {
            $row['pid'] = self::remapId($maps['nodes'] ?? [], (int)$row['pid'], '分类父节点', true);
            $row['select_count'] = 0;
        } elseif ($table === 'recycle_check_group' || $table === 'recycle_check_field') {
            $row['template_id'] = self::remapId($maps['templates'] ?? [], (int)$row['template_id'], '质检模板');
            if ($table === 'recycle_check_field') {
                $row['group_id'] = self::remapId($maps['groups'] ?? [], (int)$row['group_id'], '质检分组', true);
            }
        } elseif ($table === 'recycle_check_option') {
            $row['field_id'] = self::remapId($maps['fields'] ?? [], (int)$row['field_id'], '质检字段');
        } elseif ($table === 'recycle_template_binding') {
            $type = (string)$row['target_type'];
            if (!in_array($type, ['global', 'model_dict'], true)) throw new CommonException('存在不支持的模板绑定类型');
            $row['target_id'] = $type === 'global' ? 0 : self::remapId($maps['nodes'] ?? [], (int)$row['target_id'], '绑定型号');
            $row['check_template_id'] = self::remapId($maps['templates'] ?? [], (int)$row['check_template_id'], '绑定模板', true);
            $row['print_template_id'] = 0;
        }
        return $row;
    }
}
