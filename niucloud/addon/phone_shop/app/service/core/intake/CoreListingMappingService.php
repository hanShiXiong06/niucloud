<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 上架信息清洗映射引擎
// +----------------------------------------------------------------------
// | ERP 上架带过来 3 样:图片 / 质检报告(qc_info) / 价格。本服务把货源(DeviceIntake)
// | 清洗整理,映射到商城商品的 6 个字段(都给默认,留扩展口):
// |   1. 服务标签  service_ids     2. 内存分类  memory_group
// |   3. 标题      goods_name      4. 副标题    sub_title
// |   5. 质检报告  qc_report       6. 发货方式  delivery_type
// |
// | 优先级:调用方 overrides > 站点配置 config > 内置默认。
// | 站点配置 = 扩展口(以后要改默认/加非默认规则,改配置即可,无需改代码)。
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\intake;

use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;
use addon\phone_shop\app\support\InspectionGoodsAttributes;
use addon\phone_shop\app\support\IntakeMaterialTask;

use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;

class CoreListingMappingService extends BaseCoreService
{
    /** 站点配置键 */
    const CONFIG_KEY = 'PHONE_SHOP_LISTING_MAPPING';

    /**
     * 内置默认配置(无站点配置时生效)。改默认走站点配置,不动这里。
     */
    public static function defaultConfig(): array
    {
        return [
            // 标题模板:可用占位 {brand} {model} {memory} {color} {condition} {imei}
            'title_template'        => '{brand} {model} {memory} {color}',
            // 副标题模板:同上占位 + {warranty}
            'subtitle_template'     => '{condition} · 一机一检 · {warranty}',
            'warranty_text'         => '七天质保',
            // 服务标签(service_ids):默认挂哪些;空=不挂。这是主要扩展口之一
            'default_service_ids'   => [],
            // 商品角标(label_ids):默认挂哪些;空=不挂
            'default_label_ids'     => [],
            // 发货方式(delivery_type):express 快递 / store 到店自提 等
            'default_delivery_type' => ['express'],
            // 内存归一化别名(扩展口):如 ['8+256' => '8GB+256GB']
            'memory_aliases'        => [],
            // 质检报告
            'qc_report_enabled'     => true,
            'qc_report_title'       => '官方质检报告',
            // 质检报告里要隐藏的项(扩展口):如不想展示某检测项
            'qc_hidden_keys'        => [],
        ];
    }

    /**
     * 读取站点配置(合并默认),供后台编辑与映射使用。
     */
    public function getConfig(int $siteId): array
    {
        $saved = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        $config = self::defaultConfig();
        if (is_array($saved) && !empty($saved)) {
            $config = array_merge($config, $saved);
        }
        return $config;
    }

    /**
     * 保存站点配置(扩展口入口)。只接受已知键,避免脏数据。
     */
    public function setConfig(int $siteId, array $params): bool
    {
        $allow = array_keys(self::defaultConfig());
        $value = [];
        foreach ($allow as $k) {
            if (array_key_exists($k, $params)) {
                $value[$k] = $params[$k];
            }
        }
        (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $value);
        return true;
    }

    /**
     * 核心:把一条货源清洗映射成商城商品的 6 字段(+ 清洗后的图片/价格)。
     *
     * @param array $intake   DeviceIntake 行(model_name/brand_name/memory/color/condition_grade/imei/images/qc_info/各价)
     * @param array $overrides 调用方覆盖(人工表单),最高优先级
     * @param array|null $config 站点配置;不传则用默认(调用方可先 getConfig 再传入,省一次查询)
     * @return array {goods_name, sub_title, memory_group, service_ids, label_ids, delivery_type,
     *                qc_report:{title,items[],text,enabled}, condition_grade, images[], goods_cover, price}
     */
    public function mapIntake(array $intake, array $overrides = [], ?array $config = null): array
    {
        $config = $config ?: self::defaultConfig();
        $qc = $this->normalizeQc($intake['qc_info'] ?? []);
        $facts = InspectionGoodsAttributes::fromSnapshot($qc, IntakeMaterialTask::payload($intake['hidden_check_keys'] ?? []));

        // ---- 基础字段(顶层优先,qc_info 兜底)----
        $brand     = $this->pick($overrides, 'brand_name', $intake['brand_name'] ?? '');
        $model     = $this->pick($overrides, 'model_name', $intake['model_name'] ?? '');
        // 表单统一使用 device_color，兼容旧调用仍传 color。
        $colorOverride = $overrides['device_color'] ?? ($overrides['color'] ?? null);
        $color     = $colorOverride !== null && trim((string)$colorOverride) !== ''
            ? $colorOverride
            : (trim((string)($intake['color'] ?? '')) !== '' ? $intake['color'] : ($facts['color'] ?? ''));
        $condition = $this->pick($overrides, 'condition_grade', trim((string)($intake['condition_grade'] ?? '')) !== '' ? $intake['condition_grade'] : ($facts['condition_grade'] ?? ''));
        $imei      = (string)($intake['imei'] ?? '');

        // ---- 2. 内存分类 ----
        $memoryRaw = $this->pick($overrides, 'memory', trim((string)($intake['memory'] ?? '')) !== '' ? $intake['memory'] : ($facts['memory'] ?? ''));
        $memoryGroup = $this->normalizeMemory($memoryRaw, (array)($config['memory_aliases'] ?? []));

        // ---- 占位符上下文 ----
        $ctx = [
            '{brand}'     => trim((string)$brand),
            '{model}'     => trim((string)$model),
            '{memory}'    => $memoryGroup,
            '{color}'     => trim((string)$color),
            '{condition}' => trim((string)$condition),
            '{imei}'      => $imei,
            '{warranty}'  => (string)($config['warranty_text'] ?? '七天质保'),
        ];

        // ---- 3. 标题 ----
        $goodsName = array_key_exists('goods_name', $overrides) && trim((string)$overrides['goods_name']) !== ''
            ? (string)$overrides['goods_name']
            : $this->render((string)($config['title_template'] ?? '{brand} {model} {memory} {color}'), $ctx);
        if (trim($goodsName) === '') {
            $goodsName = $this->squeeze(trim($model . ' ' . $memoryGroup . ' ' . $condition));
        }

        // ---- 4. 副标题 ----
        $subTitle = array_key_exists('sub_title', $overrides)
            ? (string)$overrides['sub_title']
            : $this->render((string)($config['subtitle_template'] ?? ''), $ctx);

        // ---- 1. 服务标签 / 角标 ----
        $serviceIds = $this->idList($overrides['service_ids'] ?? ($config['default_service_ids'] ?? []));
        $labelIds   = $this->idList($overrides['label_ids'] ?? ($config['default_label_ids'] ?? []));

        // ---- 6. 发货方式：人工选择 > ERP 交接快照 > 站点默认 ----
        // 直上架和待办确认共用此映射，不能把 ERP 带来的三种配送退回为站点默认的仅快递。
        $sourcePayload = $intake['raw_payload'] ?? [];
        if (is_string($sourcePayload)) $sourcePayload = json_decode($sourcePayload, true);
        $sourceDeliveryType = is_array($sourcePayload) ? ($sourcePayload['delivery_type'] ?? null) : null;
        $deliveryType = $overrides['delivery_type'] ?? $sourceDeliveryType ?? ($config['default_delivery_type'] ?? ['express']);
        $deliveryType = is_array($deliveryType) ? array_values(array_filter(array_map('strval', $deliveryType))) : ['express'];
        if (empty($deliveryType)) $deliveryType = ['express'];

        // ---- 5. 质检报告 ----
        $qcReport = $this->buildQcReport($qc, $config, $overrides['qc_report'] ?? null);

        // ---- 图片 / 价格 清洗(透传)----
        $images = $this->normalizeImages($intake['images'] ?? []);
        $price  = (float)$this->pick($overrides, 'price', $intake['sale_price'] ?? 0);
        $deviceAttributes = new CoreDeviceAttributeService();
        $batteryHealth = $deviceAttributes->normalizeBattery(
            array_key_exists('battery_health', $overrides) ? $overrides['battery_health']
                : ($deviceAttributes->normalizeBattery($intake['battery_health'] ?? '', false) >= 0
                    ? $intake['battery_health'] : ($facts['battery_health'] ?? '')),
            false
        );
        $warrantyFallback = (int)($intake['warranty_expire_time'] ?? 0) > 0
            ? $intake['warranty_expire_time'] : ($facts['warranty_expire_time'] ?? '');
        $warrantySource = $overrides['warranty_expire_time'] ?? $overrides['warranty_expire_date'] ?? $warrantyFallback;
        $warrantyExpire = $deviceAttributes->normalizeWarrantyExpire(
            $warrantySource,
            false
        );

        return [
            'goods_name'      => $this->squeeze($goodsName),
            'sub_title'       => $this->squeeze($subTitle),
            'memory_group'    => $memoryGroup,
            'service_ids'     => $serviceIds,
            'label_ids'       => $labelIds,
            'delivery_type'   => $deliveryType,
            'qc_report'       => $qcReport,
            'condition_grade' => trim((string)$condition),
            'device_color'    => $deviceAttributes->normalizeColor($color, false),
            'battery_health'  => $batteryHealth,
            'warranty_expire_time' => $warrantyExpire,
            'images'          => $images,
            'goods_cover'     => $images[0] ?? '',
            'price'           => $price,
        ];
    }

    // ============================ 内部清洗工具 ============================

    /** 取值:overrides 优先且非空,否则回退 */
    private function pick(array $overrides, string $key, $fallback)
    {
        if (array_key_exists($key, $overrides)) {
            $v = $overrides[$key];
            if (is_string($v)) { if (trim($v) !== '') return $v; }
            elseif ($v !== null && $v !== '' && $v !== 0 && $v !== []) return $v;
        }
        return $fallback;
    }

    /** 占位符渲染 + 折叠多余空格 */
    private function render(string $tpl, array $ctx): string
    {
        return $this->squeeze(strtr($tpl, $ctx));
    }

    /** 折叠连续空格 / 清理悬挂分隔符 */
    private function squeeze(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));
        // 清掉因占位为空产生的悬挂分隔符:" · · "、首尾的 · / -
        $s = preg_replace('/\s*·\s*(?=·)/u', '', $s);
        $s = preg_replace('/^[·\-\s]+|[·\-\s]+$/u', '', $s);
        return trim($s);
    }

    /** 内存归一化:别名优先;否则把 g/G 规范成 GB,去空格,8+256 → 8GB+256GB */
    private function normalizeMemory(string $raw, array $aliases): string
    {
        $m = trim($raw);
        if ($m === '') return '';
        $key = mb_strtolower(str_replace(' ', '', $m));
        foreach ($aliases as $ak => $av) {
            if (mb_strtolower(str_replace(' ', '', (string)$ak)) === $key) return (string)$av;
        }
        $norm = str_replace(' ', '', $m);
        // 统一大小写单位
        $norm = preg_replace_callback('/(\d+)\s*(tb|gb|g|t)\b/iu', function ($x) {
            $num = $x[1];
            $unit = strtoupper($x[2]);
            if ($unit === 'G') $unit = 'GB';
            if ($unit === 'T') $unit = 'TB';
            return $num . $unit;
        }, $norm);
        // 8+256 这种没带单位的数字加 GB
        if (preg_match('/^\d+\+\d+$/', $norm)) {
            $norm = preg_replace('/(\d+)/', '$1GB', $norm);
        } elseif (preg_match('/^\d+$/', $norm)) {
            $norm .= 'GB';
        }
        return $norm;
    }

    /** qc_info 统一成关联数组 {检测项: 值} */
    private function normalizeQc($qc): array
    {
        if (is_string($qc)) {
            $t = trim($qc);
            if ($t === '') return [];
            $d = json_decode($t, true);
            return is_array($d) ? $d : ['质检摘要' => $t];
        }
        return is_array($qc) ? $qc : [];
    }

    /** 质检报告:结构化条目 + 纯文本,过滤隐藏项 */
    private function buildQcReport(array $qc, array $config, $override): array
    {
        $enabled = !empty($config['qc_report_enabled']);
        $title = (string)($config['qc_report_title'] ?? '官方质检报告');
        if (is_array($override)) {
            // 调用方完全自定义质检报告
            $override['enabled'] = $override['enabled'] ?? $enabled;
            $override['title'] = $override['title'] ?? $title;
            return $override;
        }
        $hidden = array_map('strval', (array)($config['qc_hidden_keys'] ?? []));
        $items = [];
        $lines = [];
        foreach ($qc as $k => $v) {
            $k = (string)$k;
            if ($k === '' || in_array($k, $hidden, true)) continue;
            $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
            if (trim($val) === '') continue;
            $items[] = ['key' => $k, 'value' => $val];
            $lines[] = $k . '：' . $val;
        }
        return [
            'enabled' => $enabled,
            'title'   => $title,
            'items'   => $items,
            'text'    => implode("\n", $lines),
        ];
    }

    /** 图片归一化:数组/逗号串 → 干净的 url 数组 */
    private function normalizeImages($images): array
    {
        if (is_string($images)) {
            $t = trim($images);
            if ($t === '') return [];
            $d = json_decode($t, true);
            $images = is_array($d) ? $d : explode(',', $t);
        }
        if (!is_array($images)) return [];
        return array_values(array_filter(array_map(fn($x) => trim((string)$x), $images)));
    }

    /** 归一化 ID 列表为去重正整数数组 */
    private function idList($v): array
    {
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $x) {
            $n = (int)$x;
            if ($n > 0) $out[$n] = $n;
        }
        return array_values($out);
    }
}
