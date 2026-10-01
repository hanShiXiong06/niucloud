<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 待上架货源(后台)服务
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\admin\intake;

use addon\phone_shop\app\service\core\goods\CoreGoodsChangeLogService;

use addon\phone_shop\app\model\intake\DeviceIntake;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\Service;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Attr;
use addon\phone_shop\app\service\admin\goods\GoodsService;
use addon\phone_shop\app\service\admin\goods\SpecService;
use addon\phone_shop\app\service\core\goods\CoreGoodsDescriptionService;
use addon\phone_shop\app\service\core\intake\CoreListingMappingService;
use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;
use addon\phone_shop\app\support\IntakeMaterialTask;
use addon\phone_shop\app\support\IntakeMaterialAttributes;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/**
 * ERP 货源上架及商城资料待办。商品交易状态与资料处理状态独立。
 */
class DeviceIntakeService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceIntake();
    }

    public static function forSite(int $siteId): self
    {
        $service = new self();
        $service->site_id = $siteId;
        return $service;
    }

    /**
     * 分页列表
     */
    public function getPage(array $where = [])
    {
        $field = 'intake_id,erp_asset_id,device_id,model_name,brand_name,memory,color,battery_health,warranty_expire_time,condition_grade,imei,images,sale_price,peer_price,cost_price,qc_info,raw_payload,status,goods_id,create_time,update_time';
        $order = 'intake_id desc';
        $search = $this->model
            ->where([ [ 'site_id', '=', $this->site_id ] ])
            ->withSearch([ 'status', 'erp_asset_id', 'model_name' ], $where)
            ->field($field)->order($order)
            ->append([ 'status_name' ]);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $search->where([
                [ 'model_name|imei|erp_asset_id', 'like', '%' . $keyword . '%' ],
            ]);
        }
        $materialStatus = (string)($where['material_status'] ?? '');
        if (in_array($materialStatus, ['pending', 'processing', 'completed'], true)) {
            $search->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(raw_payload),raw_payload,'{}'), '$._material_task.status')) = ?", [$materialStatus]);
        }
        $page = $this->pageQuery($search);
        $ids = array_values(array_filter(array_column($page['data'], 'goods_id')));
        $goodsMap = $ids === [] ? [] : (new Goods())->where('site_id', $this->site_id)->whereIn('goods_id', $ids)
            ->field('goods_id,status,stock,sale_status,is_online_sellable,memory_group,device_color,condition_grade,battery_health,warranty_expire_time')->select()->toArray();
        $goodsMap = array_column($goodsMap, null, 'goods_id');
        foreach ($page['data'] as &$row) {
            $row = $this->decorateMaterial($row, $goodsMap[(int)$row['goods_id']] ?? []);
            unset($row['raw_payload']);
        }
        unset($row);
        return $page;
    }

    /**
     * 详情
     */
    public function getInfo(int $intake_id)
    {
        $row = $this->model
            ->where([ [ 'intake_id', '=', $intake_id ], [ 'site_id', '=', $this->site_id ] ])
            ->append([ 'status_name' ])
            ->findOrEmpty()->toArray();
        if ($row === []) throw new AdminException('货源不存在');
        $goods = (int)$row['goods_id'] > 0 ? (new Goods())->where('site_id', $this->site_id)->where('goods_id', (int)$row['goods_id'])->findOrEmpty()->toArray() : [];
        $row = $this->decorateMaterial($row, $goods);
        unset($row['raw_payload']);
        return $row;
    }

    /**
     * 忽略或恢复尚未建品的货源，已建品状态只能由真实建品动作产生。
     */
    public function setStatus(int $intake_id, int $status, int $goods_id = 0)
    {
        if (!in_array($status, [DeviceIntake::STATUS_PENDING, DeviceIntake::STATUS_IGNORED], true) || $goods_id > 0) {
            throw new AdminException('已建品状态由真实商品生成，不支持手工标记');
        }
        return Db::transaction(function () use ($intake_id, $status): bool {
            $row = $this->model->where('site_id', $this->site_id)->where('intake_id', $intake_id)->lock(true)->findOrEmpty();
            if ($row->isEmpty()) throw new AdminException('货源不存在');
            if ((int)$row->goods_id > 0 || (int)$row->status === DeviceIntake::STATUS_BUILT) {
                throw new AdminException('商品已经建立，请在商品页处理上下架；资料待办单独保存，不要重新建品');
            }
            $row->save(['status' => $status, 'update_time' => time()]);
            return true;
        });
    }

    /**
     * 待建品数量（角标用）
     */
    public function getPendingCount(): int
    {
        return $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'status', '=', DeviceIntake::STATUS_PENDING ],
        ])->count();
    }

    /**
     * 预览映射:把货源按"清洗映射引擎 + 站点配置"算出 6 字段默认值,供建品表单预填。
     * 不落库,纯计算。overrides 为表单已改动的值。
     */
    public function previewMapping(int $intake_id, array $overrides = []): array
    {
        $intake = $this->model->where([ [ 'intake_id', '=', $intake_id ], [ 'site_id', '=', $this->site_id ] ])->findOrEmpty();
        if ($intake->isEmpty()) throw new AdminException('货源不存在');

        $mapper = new CoreListingMappingService();
        $config = $mapper->getConfig($this->site_id);
        $mapped = $mapper->mapIntake($intake->toArray(), $overrides, $config);

        // 服务标签可选项(供下拉)
        $mapped['service_options'] = (new Service())
            ->where([ [ 'site_id', '=', $this->site_id ] ])
            ->field('service_id,service_name')->select()->toArray();
        // 三价兜底回显
        $mapped['sale_price']   = (float) $intake['sale_price'];
        $mapped['cost_price']   = (float) $intake['cost_price'];
        $mapped['peer_price']   = (float) $intake['peer_price'];
        // 已经人工对应过的分类/规格，下次进入待办时直接预填；渠道只读映射结果，
        // 不直接查询 ERP 私有表，也不会把商城值反写为 ERP 主数据。
        $channelMapping = $this->resolveErpChannelMapping($intake->toArray());
        $raw = $this->decodeArray($intake['raw_payload']);
        if (($raw['category_source'] ?? '') === 'manual_asset') {
            $mapped['goods_category'] = array_values(array_filter(array_map('intval', (array)($raw['goods_category'] ?? []))));
        } elseif (!empty($channelMapping['category_ids'])) {
            $mapped['goods_category'] = array_values(array_map('intval', (array)$channelMapping['category_ids']));
        } else {
            $raw = $this->decodeArray($intake['raw_payload']);
            $mapped['goods_category'] = array_values(array_filter(array_map('intval', (array)($raw['goods_category'] ?? []))));
        }
        if (trim((string)($channelMapping['specs']['memory'] ?? '')) !== '') {
            $mapped['memory_group'] = trim((string)$channelMapping['specs']['memory']);
        }
        if (trim((string)($channelMapping['specs']['condition_grade'] ?? '')) !== '') {
            $mapped['condition_grade'] = trim((string)$channelMapping['specs']['condition_grade']);
        }
        $mapped['channel_mapping'] = $channelMapping;
        // 结构化质检(带级别/突出项/异常),供前端用公共质检面板渲染,替代纯文本 qc_report.text
        $mapped['check']        = $this->enrichedCheck((int) $intake['device_id'], $intake['hidden_check_keys'] ?? [], $intake['qc_info'] ?? []);
        // 副标题默认用"质检摘要"(模板勾选的≤5个设备摘要字段)拼成;用户在表单显式传了 sub_title 才不覆盖
        if (empty($overrides[ 'sub_title' ])) {
            $sub = $this->subTitleFromSummary($mapped['check']);
            if ($sub !== '') $mapped['sub_title'] = $sub;
        }
        $mapped['material_task'] = IntakeMaterialTask::read($intake['raw_payload']);
        $mapped['basic_first'] = !empty($raw['basic_first']) ? 1 : 0;
        return $mapped;
    }

    /**
     * 取货源关联回收设备的"带级别"质检数据(复用 hsx_recycle 的增强)。跨插件,class_exists 保护,失败返空。
     * @param int $deviceId 货源关联的回收设备ID
     * @return array
     */
    private function enrichedCheck(int $deviceId, $hiddenKeys = [], $qcSnapshot = []): array
    {
        $snapshot = $this->decodeArray($qcSnapshot);
        foreach (['check_meta', 'report'] as $key) {
            $candidate = $this->decodeArray($snapshot[$key] ?? []);
            if (!empty($candidate['result_items']) || !empty($candidate['summary_fields'])) {
                return $this->filterHiddenFromCheck($candidate, $hiddenKeys);
            }
        }
        if (!empty($snapshot['result_items']) || !empty($snapshot['summary_fields'])) {
            return $this->filterHiddenFromCheck($snapshot, $hiddenKeys);
        }
        if ($deviceId <= 0) {
            return [];
        }
        $cls = '\\addon\\hsx_recycle\\app\\service\\admin\\order\\RecycleDeviceService';
        if (!class_exists($cls)) {
            return [];
        }
        try {
            $check = (new $cls())->enrichedCheckMetaForDevice($deviceId);
            return $this->filterHiddenFromCheck($check, $hiddenKeys);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function decodeArray($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** 解析"隐藏质检项"为字段名数组(兼容 json 字符串 / 数组) */
    private function parseHiddenKeys($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn($v) => trim((string) $v), $value), static fn($v) => $v !== ''));
    }

    /**
     * 按定价员隐藏的字段名,从结构化质检里剔除对应项(只影响商城买家版 qc_report)。
     * 同步过滤 result_items / abnormal_items / summary_fields 并重算 severity_summary。
     */
    private function filterHiddenFromCheck(array $check, $hiddenKeys): array
    {
        $hidden = $this->parseHiddenKeys($hiddenKeys);
        if (empty($hidden) || empty($check)) {
            return $check;
        }
        $set = array_flip($hidden);
        $keep = static function ($arr) use ($set) {
            if (!is_array($arr)) {
                return $arr;
            }
            return array_values(array_filter($arr, static fn($it) => !(is_array($it) && isset($set[trim((string) ($it['field_name'] ?? ''))]))));
        };
        foreach (['result_items', 'abnormal_items', 'summary_fields'] as $k) {
            if (isset($check[$k])) {
                $check[$k] = $keep($check[$k]);
            }
        }
        if (isset($check['result_items']) && is_array($check['result_items'])) {
            $sum = ['abnormal' => 0, 'general' => 0, 'normal' => 0];
            foreach ($check['result_items'] as $it) {
                $s = (string) ($it['severity'] ?? 'normal');
                if (isset($sum[$s])) {
                    $sum[$s]++;
                }
            }
            $check['severity_summary'] = $sum;
        }
        return $check;
    }

    /**
     * 把质检摘要(质检模板里勾选的 ≤5 个"设备摘要"字段)拼成商品副标题。
     * 取 summary_fields 的 label,用 " · " 连接;无摘要返回空串(由调用方决定是否回退默认副标题)。
     * @param array $check enrichedCheck 结果(含 summary_fields)
     */
    private function subTitleFromSummary(array $check): string
    {
        $fields = $check[ 'summary_fields' ] ?? [];
        if (!is_array($fields) || empty($fields)) {
            return '';
        }
        $parts = [];
        foreach ($fields as $f) {
            $label = trim((string) ($f[ 'label' ] ?? ''));
            if ($label !== '') {
                $parts[] = $label;
            }
        }
        return implode(' · ', $parts);
    }

    /**
     * 由货源建品并上架（一台一 goods + 一 sku）。
     * 字段由"清洗映射引擎"按 站点配置/默认 自动生成,表单传入的值作为 overrides 覆盖(扩展口)。
     * 复用 GoodsService::add() 保证商品有效，再补二手机字段、回填货源。
     * @param array $data intake_id + 任意可覆盖项：goods_name, sub_title, brand_id, goods_category(数组),
     *                    label_ids[], service_ids[], memory, condition_grade, delivery_type[],
     *                    price, market_price, cost_price, goods_desc, qc_report, status
     * @return int goods_id
     */
    public function build(array $data): int
    {
        return Db::transaction(fn(): int => $this->buildLocked($data));
    }

    private function buildLocked(array $data): int
    {
        $intake_id = (int) ($data[ 'intake_id' ] ?? 0);
        $intake = $this->model->where([ [ 'intake_id', '=', $intake_id ], [ 'site_id', '=', $this->site_id ] ])->lock(true)->findOrEmpty();
        if ($intake->isEmpty()) throw new AdminException('货源不存在');
        if ((int)$intake['goods_id'] > 0 || (int)$intake['status'] === DeviceIntake::STATUS_BUILT) throw new AdminException('该货源已建品，请勿重复；补充资料不会新建商品');
        if ((int)$intake['status'] === DeviceIntake::STATUS_IGNORED) throw new AdminException('该货源已忽略，请先恢复后再上架');
        $data['goods_category'] = $this->validCategoryIds((array)($data['goods_category'] ?? []));

        // ---- 清洗映射(表单值作为 overrides,引擎填默认)----
        $mapper = new CoreListingMappingService();
        $config = $mapper->getConfig($this->site_id);
        $overrides = array_intersect_key($data, array_flip([
            'goods_name', 'sub_title', 'memory', 'condition_grade', 'device_color', 'battery_health', 'warranty_expire_time', 'warranty_expire_date', 'service_ids', 'label_ids',
            'delivery_type', 'price', 'qc_report', 'brand_name', 'model_name', 'color', 'attr_ids', 'attr_format',
        ]));
        $m = $mapper->mapIntake($intake->toArray(), $overrides, $config);
        $categoryPath = $data['goods_category'];
        $m = IntakeMaterialAttributes::matchKnownOptions($m, SpecService::forSite((int)$this->site_id)
            ->optionsForCategory((int)end($categoryPath), $categoryPath));
        if ((float)$m['price'] <= 0 || !is_finite((float)$m['price'])) throw new AdminException('请先完成有效销售定价，尚未上架');
        if (empty($m['images'])) throw new AdminException('请先上传商品图片，尚未上架');
        if (trim((string)$m['goods_name']) === '') throw new AdminException('请先核对商品型号，尚未上架');
        $rawForGuard = $this->decodeArray($intake['raw_payload']);
        if (!empty($rawForGuard['basic_first'])) {
            if (isset($data['pricing_base_price']) && abs((float)$data['pricing_base_price'] - (float)$intake['peer_price']) > .001) {
                throw new AdminException('基准售价由 ERP 定价岗位维护，请回 ERP 调价，运营补资料不改变销售价格');
            }
            $data['pricing_base_price'] = (float)$intake['peer_price'];
            // 分岗模式只由 ERP 负责定价/成本；运营补分类不能顺带改写这些业务事实。
            if ((int)round((float)$m['price'] * 100) !== (int)round((float)$intake['sale_price'] * 100)) {
                throw new AdminException('销售价格由 ERP 维护，请回 ERP 调价并重新交接后上架');
            }
            $data['cost_price'] = (float)$intake['cost_price'];
            $data['market_price'] = (float)$intake['sale_price'];
            $data['status'] = 1;
            $this->assertErpBasicListingAllowed($intake->toArray());
        }

        // 结构化质检(本次只算一次,副标题与快照共用)
        $enrichedCheck = $this->enrichedCheck((int) $intake[ 'device_id' ], $intake[ 'hidden_check_keys' ] ?? [], $intake['qc_info'] ?? []);
        // 副标题默认用"质检摘要"拼成;用户显式传 sub_title 才不覆盖
        $subTitle = (string) $m[ 'sub_title' ];
        if (empty($overrides[ 'sub_title' ])) {
            $sub = $this->subTitleFromSummary($enrichedCheck);
            if ($sub !== '') $subTitle = $sub;
        }

        $images = $m[ 'images' ];
        $goods_image = implode(',', $images);

        // 商品详情与质检报告彻底分离：
        // 结构化质检只写 goods.qc_report；ERP 实拍图同时展示在轮播和详情中。
        // 详情保留人工说明，图片只引用原文件，避免重复上传或追加。
        $descriptionService = new CoreGoodsDescriptionService();
        $goods_desc = $descriptionService->sanitize((string)($data['goods_desc'] ?? ''));
        if ($goods_desc === '') $goods_desc = $descriptionService->defaultDescription();
        if ((int)$intake['erp_asset_id'] > 0) $goods_desc = $descriptionService->withProductImages($goods_desc, $images);
        $rawPayload = $intake['raw_payload'] ?? [];
        if (is_string($rawPayload)) $rawPayload = json_decode($rawPayload, true) ?: [];
        if (!is_array($rawPayload)) $rawPayload = [];

        $goods_data = [
            'goods_name'           => $m[ 'goods_name' ],
            'sub_title'            => $subTitle,
            'goods_type'           => 'real',
            'goods_image'          => $goods_image,
            'goods_cover'          => $m[ 'goods_cover' ], // add() 仅在 goods_image 非空时才设封面，这里兜底防 Undefined array key
            'goods_image_width'    => 0,
            'goods_image_height'   => 0,
            'goods_video'          => trim((string)($data['goods_video'] ?? ($rawPayload['video_url'] ?? ''))),
            'goods_category'       => $data[ 'goods_category' ] ?? [],
            'brand_id'             => (int) ($data[ 'brand_id' ] ?? 0),
            'label_ids'            => $m[ 'label_ids' ],
            'service_ids'          => $m[ 'service_ids' ],
            'supplier_id'          => 0,
            'status'               => (int) ($data[ 'status' ] ?? 1),
            'sort'                 => 0,
            'attr_ids'             => array_values(array_filter(array_map('intval', (array)($data['attr_ids'] ?? [])))),
            'attr_format'          => array_values((array)($data['attr_format'] ?? [])), // 必须是数组：编辑页 attrChange 会读 attr_format.length
            'is_gift'              => 0,
            'spec_type'            => 'single',
            'price'                => (float) $m[ 'price' ],
            'pricing_base_price'   => (float) ($data['pricing_base_price'] ?? $intake['peer_price'] ?? 0),
            'market_price'         => (float) ($data[ 'market_price' ] ?? 0) ?: (float) $m[ 'price' ],
            'cost_price'           => (float) ($data[ 'cost_price' ] ?? $intake[ 'cost_price' ]),
            'weight'               => 0,
            'volume'               => 0,
            'stock'                => 1,
            'sku_no'               => '', // IMEI 不唯一，建后单独写，避开 verifySkuNo 查重
            'unit'                 => '台',
            'virtual_sale_num'     => 0,
            'is_limit'             => 0,
            'limit_type'           => 1,
            'max_buy'              => 0,
            'min_buy'              => 0,
            'goods_spec_format'    => '',
            'goods_sku_data'       => '',
            'delivery_type'        => $m[ 'delivery_type' ],
            'is_free_shipping'     => 1,
            'fee_type'             => '',
            'delivery_money'       => 0,
            'delivery_template_id' => 0,
            'goods_desc'           => $goods_desc,
            'memory_group'         => (string)($m['memory_group'] ?? ''),
            'condition_grade'      => (string)($m['condition_grade'] ?? ''),
            'device_color'         => (string)($m['device_color'] ?? ''),
            'battery_health'       => (int)($m['battery_health'] ?? -1),
            'warranty_expire_time' => (int)($m['warranty_expire_time'] ?? 0),
            'member_discount'      => '',
            'poster_id'            => 0,
            'form_id'              => 0,
            'diy_detail_id'        => 0,
        ];

        // 标准 add 完成后还要补 IMEI、ERP 资产、质检快照和会员价；延迟一次，
        // 避免从站先收到字段不完整的半成品。
        $goods_data['_defer_agent_sync'] = true;
        // 事件也可能由队列触发，建品站点必须来自货源而不是当前后台请求。
        $goods_id = (new GoodsService())->addForSite($goods_data, (int)$this->site_id);

        // 补二手机字段（add 不认识这些）。device_snapshot 存原始质检 + 带级别的结构化质检(供 AI/全链路/商城渲染)。
        $qc = $intake[ 'qc_info' ];
        $snapshot = is_array($qc) ? $qc : (is_string($qc) ? (json_decode($qc, true) ?: []) : []);
        $priceSnapshot = \addon\phone_shop\app\service\core\goods\CoreTierPricingService::snapshot((new GoodsSku())->where('site_id', $this->site_id)->where('goods_id', $goods_id)->value('device_snapshot'));
        if (isset($priceSnapshot['_tier_pricing'])) $snapshot['_tier_pricing'] = $priceSnapshot['_tier_pricing'];
        // $enrichedCheck 已在前面算好(副标题与快照共用),此处直接复用,避免重复查库
        if (!empty($enrichedCheck)) {
            $snapshot[ 'check_meta' ] = $enrichedCheck; // 结构化质检:result_items(含severity)/summary_fields/abnormal_items
        }
        $device_snapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        (new GoodsSku())->where('goods_id', $goods_id)->update([
            'sku_no'          => (string) $intake[ 'imei' ],
            'erp_asset_id'    => (int) $intake[ 'erp_asset_id' ],
            'is_unique'       => 1,
            'condition_grade' => $m[ 'condition_grade' ],
            'device_snapshot' => $device_snapshot,
        ]);
        (new Goods())->where('goods_id', $goods_id)->update([
            // source 语义 = 来源站点id：ERP 入库即写本站id（主站100005入的货 source=100005，天然可铺货/联动；子站自入 source=子站id 不误触发）
            'source'             => (string) $this->site_id,
            'memory_group'       => (string) $m[ 'memory_group' ],
            'condition_grade'    => $m[ 'condition_grade' ],
            'device_color'       => (string)($m['device_color'] ?? ''),
            'battery_health'     => (int)($m['battery_health'] ?? -1),
            'warranty_expire_time' => (int)($m['warranty_expire_time'] ?? 0),
            'is_online_sellable' => 1,
            // 独立质检字段:结构化质检 JSON,供"质检报告低代码组件"渲染(与商品参数 attr_format 分离)
            'qc_report'          => !empty($enrichedCheck) ? json_encode($enrichedCheck, JSON_UNESCAPED_UNICODE) : null,
        ]);

        // 同行价 → 同行会员价:把货源 peer_price 写成"同行"会员等级的固定会员价(member_discount=fixed_price)。
        // 会员价 JSON 的键统一用"站内序号 level_no"(每站从1开始),跨站口径一致;读取端 getMemberPrice 同口径解析并兼容旧 level_id。
        $peerPrice = round((float) ($intake[ 'peer_price' ] ?? 0), 2);
        if ($peerPrice > 0 && (int)(new \addon\phone_shop\app\service\core\goods\CoreTierPricingService())->policy((int)$this->site_id)['enabled'] !== 1) {
            $peerLevelId = (int) (new \app\model\member\MemberLevel())
                ->where([ [ 'site_id', '=', $this->site_id ], [ 'level_name', '=', '同行' ] ])
                ->value('level_id');
            if ($peerLevelId > 0) {
                // level_id → 站内序号 level_no;ERP 在装时取得到,取不到则回退用 level_id 以保证不丢价
                $peerLevelNo = $peerLevelId;
                $noSvc = '\\addon\\phone_shop\\app\\service\\admin\\MemberLevelNoService';
                if (class_exists($noSvc)) {
                    $no = (int) $noSvc::idToNo($this->site_id, $peerLevelId);
                    if ($no > 0) $peerLevelNo = $no;
                }
                (new GoodsSku())->where('goods_id', $goods_id)->update([
                    'member_price' => json_encode([ 'level_' . $peerLevelNo => number_format($peerPrice, 2, '.', '') ], JSON_UNESCAPED_UNICODE),
                ]);
                (new Goods())->where('goods_id', $goods_id)->update([ 'member_discount' => 'fixed_price' ]);
            }
        }

        $pricedSku = (new GoodsSku())->where('site_id', $this->site_id)->where('goods_id', $goods_id)->findOrEmpty();
        if (!$pricedSku->isEmpty() && (int)$pricedSku->erp_asset_id > 0
            && (abs((float)$pricedSku->price - (float)$intake['sale_price']) > .001
                || abs((float)($priceSnapshot['_tier_pricing']['base_price'] ?? $intake['peer_price']) - (float)$intake['peer_price']) > .001)) {
            (new \addon\phone_shop\app\service\core\goods\CoreGoodsPriceWriteService())->syncToErp((int)$this->site_id, $pricedSku->toArray());
        }
        (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$goods_id, [], 'intake');
        // 所有商城资料补齐后再按站点关系推送。这里不是单品关注，只是主站
        // “新增商品”事件的最终落点，服务会自动处理全部启用从站。
        $distribution = ['success_count' => 0, 'failed_count' => 0, 'errors' => []];
        try {
            $agentConfig = new \addon\phone_shop\app\service\core\agent\AgentConfigService();
            if ($agentConfig->isMasterSite((int)$this->site_id)) {
                $distribution = (new \addon\phone_shop\app\service\core\agent\GoodsDistributionService())
                    ->distribute((int)$goods_id);
            }
        } catch (\Throwable $e) {
            $distribution = ['success_count' => 0, 'failed_count' => 1, 'errors' => [mb_substr($e->getMessage(), 0, 180)]];
            \think\facade\Log::write('[phone_shop ERP建品自动跟随] goods_id=' . (int)$goods_id . '失败: ' . $e->getMessage());
        }

        // 回填货源为已建品
        $rawPayload['_agent_distribution'] = $distribution;
        $this->model->where('intake_id', $intake_id)->update([
            'status'      => DeviceIntake::STATUS_BUILT,
            'goods_id'    => $goods_id,
            'raw_payload' => $rawPayload,
            'update_time' => time(),
        ]);

        // 商城运营完成分类/规格映射后回写 ERP；ERP 直上架由发起方自行闭环，避免重复流水。
        if (($data['sync_back_erp'] ?? true) !== false) event('PhoneShopListingMaterialCompleted', [
            'site_id' => (int)$this->site_id,
            'basic_first' => (int)($rawForGuard['basic_first'] ?? 0),
            'erp_asset_id' => (int)$intake['erp_asset_id'],
            'intake_id' => $intake_id,
            'goods_id' => $goods_id,
            'operator' => [
                'uid' => (int)$this->uid,
                'name' => trim((string)$this->username) ?: '商城资料运营',
            ],
            'erp_context' => $this->erpContext($intake->toArray()),
            'mapping' => [
                'category_ids' => array_values(array_filter(array_map('intval', (array)($data['goods_category'] ?? [])))),
                'category_name' => trim((string)($data['category_name'] ?? '')) ?: $this->categoryPathName((array)($data['goods_category'] ?? [])),
                'brand_id' => (int)($data['brand_id'] ?? 0),
                'label_ids' => array_values(array_filter(array_map('intval', (array)($m['label_ids'] ?? [])))),
                'service_ids' => array_values(array_filter(array_map('intval', (array)($m['service_ids'] ?? [])))),
                'memory' => (string)($m['memory_group'] ?? ''),
                'condition_grade' => (string)($m['condition_grade'] ?? ''),
                'price' => (float)($m['price'] ?? 0),
                'images' => array_values($images),
                'qc_report' => $enrichedCheck,
                'attr_format' => array_values((array)($data['attr_format'] ?? [])),
            ],
        ]);

        return $goods_id;
    }

    private function validCategoryIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) throw new AdminException('请先对应商城分类，当前设备尚未上架');
        $rows = (new Category())->where('site_id', $this->site_id)->whereIn('category_id', $ids)
            ->field('category_id,pid,is_show')->select()->toArray();
        $map = array_column($rows, null, 'category_id');
        $parent = 0;
        foreach ($ids as $id) {
            $row = $map[$id] ?? [];
            if ($row === [] || (int)$row['is_show'] !== 1 || (int)$row['pid'] !== $parent) {
                throw new AdminException('商城分类已删除、隐藏或层级不一致，请重新选择完整分类路径');
            }
            $parent = $id;
        }
        return $ids;
    }

    private function assertErpBasicListingAllowed(array $intake): void
    {
        foreach ((array)event('HsxErpBasicListingEligibility', [
            'site_id' => (int)$this->site_id, 'erp_asset_id' => (int)$intake['erp_asset_id'], 'imei' => (string)$intake['imei'],
            'sale_price' => (float)$intake['sale_price'],
        ]) as $reply) {
            if (is_array($reply) && ($reply['consumer'] ?? '') === 'hsx_erp.basic_listing'
                && ($reply['allowed'] ?? false) === true && (int)($reply['erp_asset_id'] ?? 0) === (int)$intake['erp_asset_id']) return;
        }
        throw new AdminException('ERP 上架核对未响应，请更新配套 ERP 插件；货源已保留，尚未上架');
    }

    private function decorateMaterial(array $row, array $goods): array
    {
        $raw = $this->decodeArray($row['raw_payload'] ?? []);
        $row['material_task'] = IntakeMaterialTask::read($raw);
        $row['basic_first'] = !empty($raw['basic_first']) ? 1 : 0;
        $row['agent_distribution'] = (array)($raw['_agent_distribution'] ?? []);
        // 已建品展示商城当前资料，不把旧交接快照当成运营修改后的结果。
        if ($goods !== []) {
            foreach (['memory' => 'memory_group', 'color' => 'device_color', 'condition_grade' => 'condition_grade', 'battery_health' => 'battery_health', 'warranty_expire_time' => 'warranty_expire_time'] as $target => $source) {
                if (array_key_exists($source, $goods)) $row[$target] = $goods[$source];
            }
        }
        $row['material_unknown_fields'] = IntakeMaterialTask::unknownFields($goods ?: [
            'memory_group' => $row['memory'] ?? '', 'device_color' => $row['color'] ?? '',
            'condition_grade' => $row['condition_grade'] ?? '', 'battery_health' => $row['battery_health'] ?? -1,
            'warranty_expire_time' => $row['warranty_expire_time'] ?? 0,
        ]);
        $row['listing_state'] = 'pending';
        $row['listing_state_name'] = (int)$row['status'] === DeviceIntake::STATUS_IGNORED ? '已忽略' : '尚未上架';
        if ((int)$row['goods_id'] > 0) {
            $state = $goods === [] ? 'missing' : ((string)($goods['sale_status'] ?? '') === 'sold' ? 'sold'
                : ((string)($goods['sale_status'] ?? '') === 'locked' ? 'locked'
                    : (((int)$goods['status'] !== 1 || (int)($goods['is_online_sellable'] ?? 1) !== 1) ? 'offline'
                        : ((int)($goods['stock'] ?? 0) > 0 ? 'saleable' : 'empty'))));
            $row['listing_state'] = $state;
            $row['listing_state_name'] = ['missing' => '关联商品不存在', 'sold' => '已售', 'locked' => '订单占用', 'offline' => '已下架', 'saleable' => '已上架可售', 'empty' => '库存不足'][$state];
        }
        $row['material_block_reason'] = (int)$row['goods_id'] === 0 && !empty($raw['basic_first'])
            ? ((string)($raw['basic_block_reason'] ?? '') ?: '请核对并对应商城分类后上架') : '';
        return $row;
    }

    /** 已建品仅补展示资料，绝不再次建品或改动库存、售价、资产和财务。 */
    public function materialInfo(int $intakeId): array
    {
        $intake = $this->model->where('site_id', $this->site_id)->where('intake_id', $intakeId)->findOrEmpty();
        if ($intake->isEmpty()) throw new AdminException('货源不存在');
        $goods = (new Goods())->where('site_id', $this->site_id)->where('goods_id', (int)$intake->goods_id)->findOrEmpty();
        if ($goods->isEmpty()) throw new AdminException('尚未建品或关联商品不存在，请先处理分类和上架');
        $row = $this->decorateMaterial($intake->toArray(), $goods->toArray());
        if ($row['material_task']['status'] === 'none') throw new AdminException('当前货源未启用分岗资料待办，请在商品管理中编辑');
        return [
            'intake_id' => $intakeId, 'goods_id' => (int)$goods->goods_id, 'model_name' => (string)$intake->model_name,
            'imei' => (string)$intake->imei, 'material_task' => $row['material_task'], 'listing_state_name' => $row['listing_state_name'],
            'unknown_fields' => $row['material_unknown_fields'],
            'images' => $this->decodeArray($intake->images),
            'form' => array_intersect_key($goods->toArray(), array_flip(['goods_name', 'sub_title', 'memory_group', 'device_color', 'condition_grade', 'battery_health', 'warranty_expire_time', 'attr_ids', 'attr_format'])),
            'catalog' => $this->materialCatalog($goods->toArray()),
            'check' => $this->enrichedCheck((int)$intake->device_id, $intake->hidden_check_keys ?? [], $intake->qc_info ?? []),
        ];
    }

    /** 复用商城当前分类的规格、已启用成色以及参数模板，全部限制在本站。 */
    private function materialCatalog(array $goods): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->decodeArray($goods['goods_category'] ?? [])))));
        $categories = $ids === [] ? [] : (new Category())->where('site_id', $this->site_id)->whereIn('category_id', $ids)
            ->field('category_id,category_name')->select()->toArray();
        $validIds = array_values(array_map('intval', array_column($categories, 'category_id')));
        $catalog = SpecService::forSite((int)$this->site_id)->optionsForCategory((int)end($validIds), $validIds);
        $catalog['categories'] = $categories;
        $catalog['templates'] = IntakeMaterialAttributes::templates((new Attr())->where('site_id', $this->site_id)
            ->field('attr_id,attr_name,attr_value_format,sort')->order('sort desc,attr_id desc')->select()->toArray());
        return $catalog;
    }

    public function saveMaterial(int $intakeId, array $data): array
    {
        $action = (string)($data['action'] ?? 'save');
        if (!in_array($action, ['save', 'complete'], true)) throw new AdminException('资料处理动作无效');
        $goodsId = Db::transaction(function () use ($intakeId, $data, $action): int {
            $intake = $this->model->where('site_id', $this->site_id)->where('intake_id', $intakeId)->lock(true)->findOrEmpty();
            if ($intake->isEmpty() || (int)$intake->status !== DeviceIntake::STATUS_BUILT || (int)$intake->goods_id <= 0) throw new AdminException('请先完成分类对应和上架，再补充商品资料');
            $raw = $this->decodeArray($intake->raw_payload);
            $task = IntakeMaterialTask::read($raw);
            if ($task['status'] === 'none') throw new AdminException('当前货源未启用分岗资料待办');
            if (!isset($data['revision']) || (int)$data['revision'] !== $task['revision']) throw new AdminException('资料已被其他人员更新，请重新打开后核对，未覆盖最新内容');
            $skus = (new GoodsSku())->where('site_id', $this->site_id)->where('goods_id', (int)$intake->goods_id)->order('sku_id asc')->lock(true)->select()->toArray();
            if (count($skus) !== 1 || (int)$skus[0]['erp_asset_id'] !== (int)$intake->erp_asset_id) throw new AdminException('商城与 ERP 设备关联异常，请核对后处理');
            $goods = (new Goods())->where('site_id', $this->site_id)->where('goods_id', (int)$intake->goods_id)->lock(true)->findOrEmpty();
            if ($goods->isEmpty()) throw new AdminException('关联商品不存在，请联系管理员');
            $auditBefore = (new CoreGoodsChangeLogService())->capture((int)$this->site_id, (int)$goods->goods_id);
            $catalog = $this->materialCatalog($goods->toArray());
            $save = IntakeMaterialAttributes::normalizeParameters($data, $goods->toArray(), $catalog);
            IntakeMaterialAttributes::validateChoices($data, $goods->toArray(), $catalog,
                $save['attr_ids'] ?? $this->decodeArray($goods->attr_ids));
            foreach (['goods_name' => 255, 'sub_title' => 255, 'memory_group' => 50, 'device_color' => 50, 'condition_grade' => 50] as $key => $max) {
                if (!array_key_exists($key, $data)) continue;
                if (!is_scalar($data[$key]) && $data[$key] !== null) throw new AdminException('资料格式不正确，请重新核对');
                $value = trim((string)$data[$key]);
                if (mb_strlen($value) > $max) throw new AdminException('资料内容过长，请精简后保存');
                if ($key === 'goods_name' && $value === '') throw new AdminException('商品标题不能为空');
                $save[$key] = $value;
            }
            $attributes = new CoreDeviceAttributeService();
            if (array_key_exists('battery_health', $data)) $save['battery_health'] = $attributes->normalizeBattery($data['battery_health']);
            if (array_key_exists('warranty_expire_time', $data)) $save['warranty_expire_time'] = $attributes->normalizeWarrantyExpire($data['warranty_expire_time']);
            $goods->save($save + ['update_time' => time()]);
            if (isset($save['condition_grade'])) (new GoodsSku())->where('site_id', $this->site_id)->where('sku_id', (int)$skus[0]['sku_id'])->update(['condition_grade' => $save['condition_grade']]);
            $raw = IntakeMaterialTask::transition($raw, $action, (int)$this->uid, trim((string)$this->username) ?: '商城运营', time());
            $intake->save(['raw_payload' => $raw, 'update_time' => time()]);
            (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$goods->goods_id, $auditBefore, 'material');
            return (int)$goods->goods_id;
        });
        // 资料传播在主商品保存成功后触发；失败明确反馈，不误导用户重复保存或重新上架。
        $warning = '';
        try {
            $agentConfig = new \addon\phone_shop\app\service\core\agent\AgentConfigService();
            if ($agentConfig->isMasterSite((int)$this->site_id)) {
                $this->syncMaterialToExistingProxies($goodsId);
            }
        } catch (\Throwable $e) {
            $warning = '资料已保存，代理站点资料同步暂未完成：' . $e->getMessage();
        }
        return ['saved' => true, 'goods_id' => $goodsId, 'warning' => $warning] + $this->materialInfo($intakeId);
    }

    /** 资料补充只传播至已有代理副本，不调用全量铺货，避免重写从站库存与价格。 */
    private function syncMaterialToExistingProxies(int $goodsId): void
    {
        $sites = (new \addon\phone_shop\app\model\agent\PhoneShopAgent())->where('master_site_id', $this->site_id)->where('status', 1)->column('agent_site_id');
        if ($sites === []) return;
        $master = (new Goods())->where('site_id', $this->site_id)->where('goods_id', $goodsId)->findOrEmpty();
        if ($master->isEmpty()) return;
        $fields = array_intersect_key($master->toArray(), array_flip(['goods_name', 'sub_title', 'memory_group', 'device_color', 'condition_grade', 'battery_health', 'warranty_expire_time', 'attr_ids', 'attr_format']));
        $proxies = (new Goods())->whereIn('site_id', $sites)->where('source', (string)$this->site_id)->where('source_goods_id', $goodsId)->field('site_id,goods_id')->select()->toArray();
        Db::transaction(function () use ($proxies, $fields, $goodsId): void {
            $refs = new \addon\phone_shop\app\service\core\agent\RefDataSyncService();
            foreach ($proxies as $proxy) {
                $where = [['site_id', '=', (int)$proxy['site_id']], ['goods_id', '=', (int)$proxy['goods_id']]];
                $proxyGoods = (new Goods())->where($where)->where('source', (string)$this->site_id)
                    ->where('source_goods_id', $goodsId)->lock(true)->findOrEmpty();
                if ($proxyGoods->isEmpty()) continue;
                $auditBefore = (new CoreGoodsChangeLogService())->capture((int)$proxy['site_id'], (int)$proxy['goods_id']);
                $proxyFields = $fields;
                $mappedIds = [];
                foreach ($this->decodeArray($fields['attr_ids'] ?? []) as $id) {
                    $mappedIds[(int)$id] = $refs->resolveAgentRefId('attr', (int)$this->site_id, (int)$proxy['site_id'], (int)$id);
                    if ($mappedIds[(int)$id] <= 0) throw new AdminException('代理站点商品参数模板未对应，未覆盖代理资料');
                }
                if (array_key_exists('attr_ids', $fields)) $proxyFields['attr_ids'] = array_values($mappedIds);
                if (array_key_exists('attr_format', $fields)) {
                    $proxyFields['attr_format'] = $this->decodeArray($fields['attr_format']);
                    foreach ($proxyFields['attr_format'] as &$parameter) {
                        $attrId = (int)($parameter['attr_id'] ?? 0);
                        if ($attrId > 0) {
                            if (!isset($mappedIds[$attrId])) throw new AdminException('代理站点商品参数缺少模板对应，未覆盖代理资料');
                            $parameter['attr_id'] = $mappedIds[$attrId];
                        }
                    }
                    unset($parameter);
                }
                $proxyGoods->save($proxyFields + ['update_time' => time()]);
                (new GoodsSku())->where($where)->update(['condition_grade' => (string)($fields['condition_grade'] ?? '')]);
                (new CoreGoodsChangeLogService())->record((int)$proxy['site_id'], (int)$proxy['goods_id'], $auditBefore, 'agent_sync', ['uid' => 0, 'name' => '主站资料同步']);
            }
        });
    }

    private function resolveErpChannelMapping(array $intake): array
    {
        $event = [
            'site_id' => (int)$this->site_id,
            'channel_key' => 'phone_shop',
            'erp_asset_id' => (int)($intake['erp_asset_id'] ?? 0),
            'erp_context' => $this->erpContext($intake),
        ];
        try {
            foreach ((array)event('HsxErpChannelMappingResolve', $event) as $result) {
                if (is_array($result) && (string)($result['channel_key'] ?? '') === 'phone_shop') return $result;
            }
        } catch (\Throwable $e) {
            // ERP 未安装或尚未执行升级时，商城仍可使用自己的人工表单，不阻断建品。
        }
        return [];
    }

    private function erpContext(array $intake): array
    {
        $raw = $this->decodeArray($intake['raw_payload'] ?? []);
        $context = $this->decodeArray($raw['erp_context'] ?? []);
        if ($context !== []) return $context;
        return [
            'category_path' => (string)($raw['category_path'] ?? ''),
            'memory' => (string)($raw['memory'] ?? $intake['memory'] ?? ''),
            'condition_grade' => (string)($raw['condition_grade'] ?? $intake['condition_grade'] ?? ''),
            'specs' => $this->decodeArray($raw['spec_meta'] ?? []),
        ];
    }

    private function categoryPathName(array $categoryIds): string
    {
        $ids = array_values(array_filter(array_map('intval', $categoryIds)));
        if ($ids === []) return '';
        $rows = Db::name('phone_shop_goods_category')->where('site_id', (int)$this->site_id)
            ->whereIn('category_id', $ids)->column('category_name', 'category_id');
        $names = [];
        foreach ($ids as $id) {
            $name = trim((string)($rows[$id] ?? ''));
            if ($name !== '') $names[] = $name;
        }
        return implode('/', $names);
    }
}
