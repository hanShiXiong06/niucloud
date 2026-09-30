<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\agent\PhoneShopAgent;
use addon\phone_shop\app\model\intake\DeviceIntake;
use addon\phone_shop\app\support\IntakeMaterialTask;
use addon\phone_shop\app\support\GoodsSource;

/** 按页批量读取资料进度；代理只展示主站进度，不复制待办或开放跨站编辑。 */
final class CoreGoodsMaterialService
{
    public function decorate(int $siteId, array $rows): array
    {
        if ($rows === []) return [];
        $hasProxy = array_filter($rows, static fn(array $row): bool => (int)($row['site_id'] ?? 0) === $siteId && !GoodsSource::isLocal($siteId, $row));
        $allowedMasters = $hasProxy === [] ? [] : array_map('intval', PhoneShopAgent::where('agent_site_id', $siteId)
            ->where('status', 1)->column('master_site_id'));
        $targets = [];
        foreach ($rows as $index => &$row) {
            $row['material_task'] = ['status' => 'none', 'status_name' => '', 'intake_id' => 0, 'can_edit' => 0, 'from_master' => 0];
            if ((int)($row['site_id'] ?? 0) !== $siteId) continue;
            $proxy = !GoodsSource::isLocal($siteId, $row);
            $owner = $proxy ? (int)($row['source'] ?? 0) : $siteId;
            $goodsId = (int)($proxy ? ($row['source_goods_id'] ?? 0) : ($row['goods_id'] ?? 0));
            if ($goodsId <= 0 || ($proxy && !in_array($owner, $allowedMasters, true))) continue;
            $targets[$owner][$goodsId][] = $index;
        }
        unset($row);
        foreach ($targets as $owner => $goodsIds) {
            $intakes = DeviceIntake::where('site_id', $owner)->where('status', DeviceIntake::STATUS_BUILT)
                ->whereIn('goods_id', array_keys($goodsIds))
                ->field("intake_id,goods_id,JSON_EXTRACT(IF(JSON_VALID(raw_payload),raw_payload,'{}'), '$._material_task') AS material_data")
                ->select()->toArray();
            foreach ($intakes as $intake) {
                $task = IntakeMaterialTask::read(['_material_task' => IntakeMaterialTask::payload($intake['material_data'] ?? [])]);
                if ($task['status'] === 'none') continue;
                foreach ($goodsIds[(int)$intake['goods_id']] as $index) {
                    $rows[$index]['material_task'] = [
                        'status' => $task['status'], 'status_name' => $task['status_name'],
                        'intake_id' => $owner === $siteId ? (int)$intake['intake_id'] : 0,
                        'can_edit' => $owner === $siteId ? 1 : 0, 'from_master' => $owner === $siteId ? 0 : 1,
                    ];
                }
            }
        }
        return $rows;
    }
}
