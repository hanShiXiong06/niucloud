<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use app\model\sys\SysArea;
use core\exception\CommonException;

/**
 * 项目参与地区规则。
 *
 * 地区主数据始终复用系统 sys_area；项目只保存允许参与的地区 ID。
 * 省、市、区任意一层被选中，都代表该节点及其全部下级地区可参与。
 */
final class ProjectCenterAreaEligibilityService
{
    private const MAX_SCOPE_COUNT = 4000;

    public function normalizeProjectConfig(array $config): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)($config['allowed_area_ids'] ?? [])
        ), static fn(int $id): bool => $id > 0)));
        if (count($ids) > self::MAX_SCOPE_COUNT) {
            throw new CommonException('可参与地区最多选择 ' . self::MAX_SCOPE_COUNT . ' 个节点');
        }

        return [
            'enabled' => !empty($config['enabled']) ? 1 : 0,
            'allowed_area_ids' => $ids,
            'title' => $this->text($config['title'] ?? '', '先查询您的地区是否可参与', 50),
            'tips' => $this->text($config['tips'] ?? '', '选择门店所在省、市、区，确认可以参与后再付款。', 200),
            'button_text' => $this->text($config['button_text'] ?? '', '查询是否可以参加', 20),
            'eligible_text' => $this->text($config['eligible_text'] ?? '', '当前地区可以参加，请继续完成付款。', 200),
            'ineligible_text' => $this->text($config['ineligible_text'] ?? '', '当前地区暂不在可参与范围内，请联系工作人员确认。', 200),
            'enabled_at' => max(0, (int)($config['enabled_at'] ?? 0)),
        ];
    }

    /** 保存项目前执行严格校验，并去掉已被上级范围覆盖的重复节点。 */
    public function validateProjectConfig(array $config): array
    {
        $rule = $this->normalizeProjectConfig($config);
        $ids = (array)$rule['allowed_area_ids'];
        if (empty($rule['enabled'])) {
            $rule['allowed_area_ids'] = $this->existingMinimalIds($ids, false);
            return $rule;
        }
        if ($ids === []) throw new CommonException('开启地区参与限制后，请至少选择一个可参与地区');
        $rule['allowed_area_ids'] = $this->existingMinimalIds($ids, true);
        if ($rule['allowed_area_ids'] === []) throw new CommonException('可参与地区不存在，请重新选择');
        return $rule;
    }

    public function projectConfig($project): array
    {
        $data = is_array($project) ? $project : (method_exists($project, 'toArray') ? $project->toArray() : []);
        $config = is_array($data['config_json'] ?? null) ? $data['config_json'] : [];
        return $this->normalizeProjectConfig((array)($config['area_eligibility'] ?? []));
    }

    /** 客户项目页只获得展示信息，不下发白名单 ID，避免枚举内部经营范围。 */
    public function publicSummary($project): array
    {
        $rule = $this->projectConfig($project);
        return [
            'enabled' => (int)$rule['enabled'],
            'title' => (string)$rule['title'],
            'tips' => (string)$rule['tips'],
            'button_text' => (string)$rule['button_text'],
            'eligible_text' => (string)$rule['eligible_text'],
            'ineligible_text' => (string)$rule['ineligible_text'],
            'scope_count' => count((array)$rule['allowed_area_ids']),
        ];
    }

    /**
     * 查询当前地区是否可参与。
     *
     * @param array $selection 支持 province_id/city_id/district_id，或 area-select 组件的对象结构。
     */
    public function query($project, array $selection): array
    {
        $rule = $this->projectConfig($project);
        if (empty($rule['enabled'])) {
            return [
                'enabled' => 0,
                'eligible' => 1,
                'status' => 'unrestricted',
                'message' => '当前项目未限制参与地区',
                'selection' => [],
                'full_name' => '',
                'matched_scope' => [],
                'checked_at' => time(),
            ];
        }

        $normalized = $this->normalizeSelection($selection);
        $allowed = array_fill_keys(array_map('intval', (array)$rule['allowed_area_ids']), true);
        $matched = [];
        foreach (['district', 'city', 'province'] as $levelName) {
            $row = (array)($normalized[$levelName] ?? []);
            if ((int)($row['id'] ?? 0) > 0 && isset($allowed[(int)$row['id']])) {
                $matched = $row;
                break;
            }
        }
        $eligible = $matched !== [];

        return [
            'enabled' => 1,
            'eligible' => $eligible ? 1 : 0,
            'status' => $eligible ? 'eligible' : 'ineligible',
            'message' => $eligible ? (string)$rule['eligible_text'] : (string)$rule['ineligible_text'],
            'selection' => $normalized,
            'full_name' => implode(' / ', array_values(array_filter([
                (string)($normalized['province']['name'] ?? ''),
                (string)($normalized['city']['name'] ?? ''),
                (string)($normalized['district']['name'] ?? ''),
            ]))),
            'matched_scope' => $matched,
            'checked_at' => time(),
        ];
    }

    public function assertEligible($project, array $selection): array
    {
        $result = $this->query($project, $selection);
        if (!empty($result['enabled']) && empty($result['eligible'])) {
            throw new CommonException((string)$result['message']);
        }
        return $result;
    }

    /** 历史已付款工单继续修改时保留办理资格，避免新规则反向卡住存量客户。 */
    public function historicalSnapshot(): array
    {
        return [
            'enabled' => 1,
            'eligible' => 1,
            'status' => 'historical_grandfathered',
            'message' => '该客户已在地区限制启用前进入办理流程，按历史规则继续办理。',
            'selection' => [],
            'full_name' => '',
            'matched_scope' => [],
            'checked_at' => time(),
        ];
    }

    private function normalizeSelection(array $selection): array
    {
        $provinceId = $this->selectionId($selection, 'province');
        $cityId = $this->selectionId($selection, 'city');
        $districtId = $this->selectionId($selection, 'district');
        if ($provinceId <= 0) throw new CommonException('请选择门店所在省份');

        $requestedIds = array_values(array_unique(array_filter([$provinceId, $cityId, $districtId])));
        $rows = SysArea::whereIn('id', $requestedIds)->field('id,pid,name,level')->select()->toArray();
        $map = [];
        foreach ($rows as $row) $map[(int)$row['id']] = $row;

        $province = $map[$provinceId] ?? [];
        if ((int)($province['level'] ?? 0) !== 1 || (int)($province['pid'] ?? -1) !== 0) {
            throw new CommonException('省份信息不正确，请重新选择');
        }

        $city = [];
        $district = [];
        $provinceHasChildren = SysArea::where('pid', '=', $provinceId)->count() > 0;
        if ($provinceHasChildren) {
            if ($cityId <= 0) throw new CommonException('请选择门店所在城市');
            $city = $map[$cityId] ?? [];
            if ((int)($city['level'] ?? 0) !== 2 || (int)($city['pid'] ?? 0) !== $provinceId) {
                throw new CommonException('城市与省份不匹配，请重新选择');
            }
            $cityHasChildren = SysArea::where('pid', '=', $cityId)->count() > 0;
            if ($cityHasChildren) {
                if ($districtId <= 0) throw new CommonException('请选择门店所在区县');
                $district = $map[$districtId] ?? [];
                if ((int)($district['level'] ?? 0) !== 3 || (int)($district['pid'] ?? 0) !== $cityId) {
                    throw new CommonException('区县与城市不匹配，请重新选择');
                }
            }
        }

        return [
            'province' => $this->publicArea($province),
            'city' => $this->publicArea($city),
            'district' => $this->publicArea($district),
        ];
    }

    private function selectionId(array $selection, string $key): int
    {
        if (isset($selection[$key . '_id'])) return max(0, (int)$selection[$key . '_id']);
        $value = $selection[$key] ?? [];
        return is_array($value) ? max(0, (int)($value['id'] ?? 0)) : max(0, (int)$value);
    }

    private function publicArea(array $row): array
    {
        if ($row === []) return [];
        return [
            'id' => (int)$row['id'],
            'pid' => (int)$row['pid'],
            'name' => (string)$row['name'],
            'level' => (int)$row['level'],
        ];
    }

    private function existingMinimalIds(array $ids, bool $strict): array
    {
        if ($ids === []) return [];
        $rows = SysArea::whereIn('id', $ids)->field('id,pid,level')->select()->toArray();
        $rowMap = [];
        foreach ($rows as $row) {
            if ((int)$row['level'] < 1 || (int)$row['level'] > 3) continue;
            $rowMap[(int)$row['id']] = $row;
        }
        if ($strict && count($rowMap) !== count($ids)) {
            throw new CommonException('部分可参与地区已失效，请重新选择');
        }

        $selected = array_fill_keys(array_keys($rowMap), true);
        $parentCache = $rowMap;
        $result = [];
        foreach ($rowMap as $id => $row) {
            $covered = false;
            $pid = (int)$row['pid'];
            $guard = 0;
            while ($pid > 0 && $guard++ < 4) {
                if (isset($selected[$pid])) {
                    $covered = true;
                    break;
                }
                if (!isset($parentCache[$pid])) {
                    $parent = SysArea::where('id', '=', $pid)->field('id,pid,level')->findOrEmpty()->toArray();
                    $parentCache[$pid] = $parent;
                }
                $pid = (int)($parentCache[$pid]['pid'] ?? 0);
            }
            if (!$covered) $result[] = $id;
        }
        sort($result, SORT_NUMERIC);
        return $result;
    }

    private function text($value, string $default, int $length): string
    {
        $value = trim((string)$value);
        return mb_substr($value !== '' ? $value : $default, 0, $length);
    }
}
