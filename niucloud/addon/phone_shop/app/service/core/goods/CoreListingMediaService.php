<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Attr;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\Label;
use addon\phone_shop\app\model\goods\LabelGroup;
use addon\phone_shop\app\model\goods\Brand;
use addon\phone_shop\app\service\admin\goods\SpecService;
use addon\phone_shop\app\support\IntakeMaterialAttributes;
use addon\phone_shop\app\support\ListingMediaContract;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 本站标准商品图，仅显式登记后复用；不读取普通商品实拍作为图库。无结构升级。 */
class CoreListingMediaService
{
    public const CONFIG_PREFIX = 'PHONE_SHOP_LISTING_MEDIA_V1_';
    /** 当前请求实际取得的租约；不接受客户端构造的任意 RELEASE_LOCK。 */
    private static array $leases = [];

    public function catalog(int $siteId, array $selection): array
    {
        $categoryId = (int)($selection['value'] ?? 0);
        $path = array_values(array_filter(array_map('intval', (array)($selection['path'] ?? []))));
        $catalog = SpecService::forSite($siteId)->optionsForCategory($categoryId, $path);
        // 参数模板没有分类外键：只取该末级分类实际使用过的模板，不能汇总全站颜色。
        $templateIds = [];
        $used = (new Goods())->where('site_id', $siteId)->where('delete_time', 0)
            ->withSearch(['goods_category'], ['goods_category' => $categoryId])->distinct(true)->column('attr_ids');
        foreach ($used as $value) {
            foreach (IntakeMaterialAttributes::arrayValue($value) as $id) {
                if ((int)$id > 0) $templateIds[] = (int)$id;
            }
        }
        $templateIds = array_values(array_unique($templateIds));
        $catalog['templates'] = $templateIds === [] ? [] : IntakeMaterialAttributes::templates((new Attr())
            ->where('site_id', $siteId)->whereIn('attr_id', $templateIds)
            ->field('attr_id,attr_name,attr_value_format,sort')->select()->toArray());
        $choices = IntakeMaterialAttributes::options($catalog, $templateIds);
        return [
            'attributes' => ['memory_group' => $choices['memory_group'], 'device_color' => $choices['device_color']],
            'grades' => array_values((array)($catalog['grades'] ?? [])),
            'labels' => $this->labels($siteId),
            'brands' => $this->brands($siteId),
        ];
    }

    /** 标签属于本站，不依赖商品是否已选择分类；分组名称仅作展示，不自动勾选标签。 */
    public function labels(int $siteId): array
    {
        $labels = (new Label())->where('site_id', $siteId)->where('status', 1)
            ->field('label_id,label_name,group_id')->order('sort desc,label_id desc')->select()->toArray();
        $groupIds = array_values(array_unique(array_filter(array_map('intval', array_column($labels, 'group_id')))));
        $groups = $groupIds === [] ? [] : array_column((new LabelGroup())->where('site_id', $siteId)->whereIn('group_id', $groupIds)
            ->field('group_id,group_name')->select()->toArray(), 'group_name', 'group_id');
        foreach ($labels as &$label) {
            $label['label_id'] = (int)$label['label_id'];
            $groupId = (int)($label['group_id'] ?? 0);
            $groupName = trim((string)($groups[$groupId] ?? ''));
            // 未分组、原分组已删除或属于其他站点，一律收拢为同一个“其他标签”组。
            $label['group_id'] = $groupName === '' ? 0 : $groupId;
            $label['group_name'] = $groupName === '' ? '其他标签' : $groupName;
        }
        unset($label);
        return $labels;
    }

    public function brands(int $siteId): array
    {
        // 品牌表没有 status 列；未软删除且属于本站才是有效选项。
        $rows = (new Brand())->where('site_id', $siteId)->where('delete_time', 0)
            ->field('brand_id,brand_name')->order('sort desc,brand_id desc')->select()->toArray();
        foreach ($rows as &$row) $row['brand_id'] = (int)$row['brand_id'];
        unset($row);
        return $rows;
    }

    public function describe(int $siteId, array $selection, array $context): array
    {
        $catalog = $this->catalog($siteId, $selection);
        $identity = ListingMediaContract::identity($siteId, (int)$selection['value'],
            ListingMediaContract::text($context['model'] ?? '', '设备型号', 255),
            ListingMediaContract::text($context['memory'] ?? '', '容量 / 规格'),
            ListingMediaContract::text($context['color'] ?? '', '颜色'));
        $standard = ['eligible' => false, 'matched' => false, 'key' => '', 'images' => [], 'missing' => $identity['missing'], 'update_at' => 0];
        try {
            $memory = ListingMediaContract::choice($context['memory'] ?? '', $catalog['attributes']['memory_group'], '容量 / 规格', true);
            $color = ListingMediaContract::choice($context['color'] ?? '', $catalog['attributes']['device_color'], '颜色', true);
            $identity = ListingMediaContract::identity($siteId, (int)$selection['value'],
                ListingMediaContract::text($context['model'] ?? '', '设备型号', 255), $memory, $color);
            $standard = array_merge($standard, array_intersect_key($identity, array_flip(['eligible', 'key', 'missing'])));
            if ($identity['eligible']) {
                $saved = $this->readStandard($siteId, $identity);
                if ($saved !== null) $standard = array_merge($standard, ['matched' => true, 'images' => $saved['images'], 'update_at' => $saved['update_at']]);
            }
        } catch (CommonException $exception) {
            $standard['message'] = $exception->getMessage();
        }
        return $catalog + ['standard_images' => $standard];
    }

    public function resolveMedia(int $siteId, array $selection, array $context): array
    {
        $catalog = $this->catalog($siteId, $selection);
        $mode = $context['image_mode'] ?? 'standard';
        if (!is_string($mode) || !in_array($mode, ['standard', 'custom'], true)) throw new CommonException('请选择标准图或自定义实拍图');
        $memory = ListingMediaContract::choice($context['memory'] ?? '', $catalog['attributes']['memory_group'], '容量 / 规格', $mode === 'standard');
        $color = ListingMediaContract::choice($context['color'] ?? '', $catalog['attributes']['device_color'], '颜色', $mode === 'standard');
        $grade = ListingMediaContract::grade($context['grade_id'] ?? 0, $catalog['grades']);
        $model = ListingMediaContract::text($context['model'] ?? '', '设备型号', 255);
        $identity = ListingMediaContract::identity($siteId, (int)$selection['value'], $model, $memory, $color);
        $material = ['memory' => $memory, 'color' => $color, 'grade' => $grade, 'image_mode' => $mode, 'images' => [], 'standard_key' => ''];
        if (array_key_exists('battery_health', $context)) {
            $material['battery_health'] = (new CoreDeviceAttributeService())->normalizeBattery($context['battery_health']);
        }
        // 保修是本台设备的资料，不参与标准图 SKU 匹配；明确清空也需透传为 0。
        if (array_key_exists('warranty_date', $context)) {
            $material['warranty_expire_time'] = (new CoreDeviceAttributeService())->normalizeWarrantyExpire(
                ListingMediaContract::text($context['warranty_date'], '保修到期日', 32)
            );
        }
        // 仅显式提交才写入；省略沿用原选择，空数组明确不选任何标签。
        if (array_key_exists('label_ids', $context)) $material['label_ids'] = ListingMediaContract::labels($context['label_ids'], $catalog['labels']);
        if (array_key_exists('brand_id', $context)) $material['brand'] = ListingMediaContract::brand($context['brand_id'], $catalog['brands']);
        if ($mode === 'custom') {
            $material['images'] = ListingMediaContract::images($context['images'] ?? $context['image_urls'] ?? []);
            if ($material['images'] === []) throw new CommonException('请上传本台设备的商品图片');
            return ['material' => $material];
        }
        if (!$identity['eligible']) throw new CommonException('使用标准图前，请先完善' . implode('、', $identity['missing']) . '；也可以切换自定义实拍');
        $saved = $this->readStandard($siteId, $identity);
        if ($saved === null) {
            if (!in_array($context['register_standard'] ?? 0, [1, '1', true], true)) {
                throw new CommonException('当前型号、规格、颜色尚无标准图，请首次上传并确认保存为标准图，或切换自定义实拍');
            }
            $images = ListingMediaContract::images($context['images'] ?? $context['image_urls'] ?? []);
            if ($images === []) throw new CommonException('请先上传当前型号、规格、颜色的标准商品图');
            $saved = $this->withStandardLock($siteId, $identity['key'], function () use ($siteId, $identity, $images): array {
                $existing = $this->readStandard($siteId, $identity);
                if ($existing !== null) return $existing;
                $saved = ['version' => 1, 'key' => $identity['key'], 'identity' => $identity['identity'], 'images' => $images, 'update_at' => time()];
                Db::name('sys_config')->insert([
                    'site_id' => $siteId, 'config_key' => self::CONFIG_PREFIX . $identity['key'],
                    'value' => json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 1, 'create_time' => $saved['update_at'], 'update_time' => $saved['update_at'], 'addon' => 'phone_shop',
                ]);
                return $saved;
            });
        }
        // 命中时只用服务端原图库，忽略客户端提交的图片与“重新登记”标志。
        return ['material' => array_merge($material, ['images' => $saved['images'], 'standard_key' => $identity['key']])];
    }

    private function readStandard(int $siteId, array $identity): ?array
    {
        if (!$identity['eligible']) return null;
        $rows = Db::name('sys_config')->where('site_id', $siteId)->where('config_key', self::CONFIG_PREFIX . $identity['key'])
            ->master()->field('id,value,status')->limit(2)->select()->toArray();
        if ($rows === []) return null;
        if (count($rows) !== 1) throw new CommonException('当前 SKU 标准图存在重复配置，请联系管理员核对；不会自动覆盖，请先用自定义实拍');
        $row = $rows[0];
        $saved = IntakeMaterialAttributes::arrayValue($row['value']);
        if ((int)$row['status'] !== 1 || ($saved['identity'] ?? []) !== $identity['identity'] || ($saved['key'] ?? '') !== $identity['key']) {
            throw new CommonException('当前 SKU 标准图已停用或信息异常，请联系管理员核对，也可以使用自定义实拍');
        }
        $saved['images'] = ListingMediaContract::images($saved['images'] ?? []);
        if ($saved['images'] === []) throw new CommonException('当前 SKU 标准图为空，请联系管理员核对，也可以使用自定义实拍');
        $saved['update_at'] = (int)($saved['update_at'] ?? 0);
        return $saved;
    }

    /** ERP 在事务开始前取得租约，事务提交/回滚后释放，首次图库登记才真正具备原子性。 */
    public function lockMedia(int $siteId, array $selection, array $context): array
    {
        $catalog = $this->catalog($siteId, $selection);
        $memory = ListingMediaContract::choice($context['memory'] ?? '', $catalog['attributes']['memory_group'], '容量 / 规格', true);
        $color = ListingMediaContract::choice($context['color'] ?? '', $catalog['attributes']['device_color'], '颜色', true);
        $identity = ListingMediaContract::identity($siteId, (int)$selection['value'],
            ListingMediaContract::text($context['model'] ?? '', '设备型号', 255), $memory, $color);
        if (!$identity['eligible']) throw new CommonException('登记标准图前，请先完善' . implode('、', $identity['missing']));
        return ['lock_key' => $this->acquireStandardLock($siteId, $identity['key']), 'standard_key' => $identity['key']];
    }

    public function unlockMedia(int $siteId, string $lockKey): void
    {
        $prefix = 'phone_media:' . $siteId . ':';
        if ($siteId <= 0 || strlen($lockKey) > 64 || !str_starts_with($lockKey, $prefix)
            || !preg_match('/^[a-f0-9]{40}$/D', substr($lockKey, strlen($prefix)))
            || !isset(self::$leases[$lockKey]) || self::$leases[$lockKey]['site_id'] !== $siteId) {
            throw new CommonException('标准图库锁租约无效，未执行解锁');
        }
        $lease = self::$leases[$lockKey];
        try {
            $lease['connection']->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $lockKey], true);
        } finally {
            self::$leases[$lockKey]['count']--;
            if (self::$leases[$lockKey]['count'] <= 0) unset(self::$leases[$lockKey]);
        }
    }

    /** 外层有业务事务时，调用方可持有同名锁直到事务提交，避免首次登记未提交时重复插入。 */
    public function withStandardLock(int $siteId, string $key, callable $action)
    {
        $lockKey = $this->acquireStandardLock($siteId, $key);
        try { return $action(); }
        finally {
            try { $this->unlockMedia($siteId, $lockKey); }
            catch (\Throwable $exception) { Log::error('[phone_shop 标准图库锁释放失败] site_id=' . $siteId); }
        }
    }

    private function acquireStandardLock(int $siteId, string $key): string
    {
        if ($siteId <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $key)) throw new CommonException('标准图匹配信息不完整');
        $connection = Db::connect();
        $scope = (string)$connection->getConfig('database') . ':' . (string)$connection->getConfig('prefix');
        $lockKey = 'phone_media:' . $siteId . ':' . substr(hash('sha256', $scope . ':' . $siteId . ':' . $key), 0, 40);
        try { $locked = $connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired', ['lock_key' => $lockKey], true); }
        catch (\Throwable $exception) { throw new CommonException('标准图库保存保护暂不可用，请稍后重试或选择自定义实拍'); }
        if ((int)($locked[0]['acquired'] ?? 0) !== 1) throw new CommonException('相同 SKU 标准图正在首次登记，请稍后重试，不会覆盖已有图片');
        if (!isset(self::$leases[$lockKey])) self::$leases[$lockKey] = ['site_id' => $siteId, 'connection' => $connection, 'count' => 0];
        self::$leases[$lockKey]['count']++;
        return $lockKey;
    }
}
