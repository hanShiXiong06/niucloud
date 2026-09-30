<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\api\goods;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\model\coupon\CouponGoods;
use addon\phone_shop\app\model\goods\Brand;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsCollect;
use addon\phone_shop\app\model\goods\GoodsGrade;
use addon\phone_shop\app\model\goods\Label;
use addon\phone_shop\app\model\goods\LabelGroup;
use addon\phone_shop\app\model\goods\Service;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\service\api\marketing\DiscountService;
use addon\phone_shop\app\service\api\marketing\NewcomerService;
use addon\phone_shop\app\service\core\goods\CoreGoodsAccessNumService;
use addon\phone_shop\app\service\core\goods\CoreGoodsActivePriceService;
use addon\phone_shop\app\service\core\goods\CoreGoodsConfigService;
use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;
use addon\phone_shop\app\service\core\goods\CoreGoodsDescriptionService;
use addon\phone_shop\app\service\core\goods\CoreGoodsStatService;
use addon\phone_shop\app\service\core\goods\CoreGoodsLimitBuyService;
use addon\phone_shop\app\service\core\goods\CoreMemberDiscountPriceService;
use addon\phone_shop\app\service\core\goods\CoreMemberPriceService;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use app\model\member\Member;
use app\service\api\diy\DiyService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 *  商品服务层
 */
class GoodsService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Goods();
    }

    /**
     * 获取商品列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        foreach ([ 'goods_category', 'label_ids', 'service_ids', 'brand_id' ] as $multi_key) {
            $normalized = $this->normalizeMultiValue($where[$multi_key] ?? '');
            if (!empty($normalized)) {
                $where[$multi_key] = count($normalized) === 1 ? $normalized[0] : $normalized;
            }
        }

        $field = 'site_id,goods_id,source,is_proxy,goods_name,sub_title,goods_type,goods_cover,unit,sale_num + goods.virtual_sale_num as sale_num,is_limit,limit_type,max_buy,min_buy,member_discount,virtual_receive_type,label_ids,brand_id,stock,status,sale_status,is_online_sellable,memory_group,condition_grade,device_color,battery_health,warranty_expire_time,qc_report';

        $master_site_id = ( new \addon\phone_shop\app\service\core\agent\AgentConfigService() )->getMasterSiteId();
        $is_agent_site = $this->site_id !== $master_site_id;
        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goods.site_id', '=', $this->site_id ],
            [ 'goods.is_gift', '=', GoodsDict::NOT_IS_GIFT ],
            [ 'goods.status', '=', 1 ],
            [ 'goods.sale_status', '=', 'available' ]
        ];

        // 无论自营还是代理，只有明确允许线上销售的商品才能进入小程序列表。
        // 跟随同步会把主站当前可售副本设置为 1，不能再用来源字段绕过该事实。
        $sku_where[] = [ 'goods.is_online_sellable', '=', 1 ];
        if (!empty($where['arrival_batch_id'])) {
            $batchIds = (new \addon\phone_shop\app\service\core\goods\CoreGoodsArrivalService())->batchGoodsIds((int)$this->site_id, (int)$where['arrival_batch_id']);
            $sku_where[] = ['goods.goods_id', 'in', $batchIds ?: [0]];
        }

        if (isset($where['brand_id']) && is_array($where['brand_id'])) {
            $sku_where[] = [ 'goods.brand_id', 'in', $where['brand_id'] ];
            unset($where['brand_id']);
        }

        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            // 商城前台统一搜索：商品名称、副标题或 SKU 编码。
            // 二手机的一机一码 IMEI 保存在 sku_no，必须通过已关联的默认 SKU 查询。
            $sku_where[] = [
                'goods.goods_name|goods.sub_title|goodsSku.sku_no',
                'like',
                '%' . $keyword . '%'
            ];
        }

        // 二手机:内存 / 成色等级 筛选(支持数组或逗号串)
        if (!empty($where[ 'memory_group' ])) {
            $mem = is_array($where[ 'memory_group' ]) ? $where[ 'memory_group' ] : array_filter(array_map('trim', explode(',', (string) $where[ 'memory_group' ])));
            if (!empty($mem)) $sku_where[] = [ 'goods.memory_group', 'in', array_values($mem) ];
        }
        if (!empty($where[ 'condition_grade' ])) {
            $grade = is_array($where[ 'condition_grade' ]) ? $where[ 'condition_grade' ] : array_filter(array_map('trim', explode(',', (string) $where[ 'condition_grade' ])));
            if (!empty($grade)) $sku_where[] = [ 'goods.condition_grade', 'in', array_values($grade) ];
        }
        if (!empty($where['device_color'])) {
            $colors = is_array($where['device_color']) ? $where['device_color'] : array_filter(array_map('trim', explode(',', (string)$where['device_color'])));
            if ($colors) $sku_where[] = ['goods.device_color', 'in', array_values($colors)];
        }
        if ((string)($where[ 'in_stock' ] ?? '') === '1') {
            $sku_where[] = [ 'goodsSku.stock', '>', 0 ];
        }

        if (!empty($where[ 'start_price' ]) && !empty($where[ 'end_price' ])) {
            $money = [ $where[ 'start_price' ], $where[ 'end_price' ] ];
            sort($money);
            $sku_where[] = [ 'goodsSku.price', 'between', $money ];
        } else if (!empty($where[ 'start_price' ])) {
            $sku_where[] = [ 'goodsSku.price', '>=', $where[ 'start_price' ] ];
        } else if (!empty($where[ 'end_price' ])) {
            $sku_where[] = [ 'goodsSku.price', '<=', $where[ 'end_price' ] ];
        }

        // 对外 source 契约：空=全部，1=本站自营，主站 site_id=主站代理。
        // warehouse 是已上线页面的旧参数，继续兼容，但 source 显式传入时优先。
        $sourceSelector = trim((string)($where['source'] ?? ''));
        if ($sourceSelector !== '' && $sourceSelector !== 'all' && $sourceSelector !== '0') {
            $this->applySourceSelector($sku_where, $sourceSelector, $master_site_id);
        } elseif (!empty($where['warehouse'])) {
            $warehouseSelector = (string)$where['warehouse'] === 'agent' ? (string)$master_site_id : '1';
            $this->applySourceSelector($sku_where, $warehouseSelector, $master_site_id);
        }

        // 查询优惠券包括的id
        if (!empty($where[ 'coupon_id' ])) {
            $coupon_goods_model = new CouponGoods();
            $coupon_list = $coupon_goods_model->where([
                [ 'coupon_id', '=', $where[ 'coupon_id' ] ]
            ])->field('goods_id,category_id')->select()->toArray();
            if (!empty($coupon_list)) {
                $goods_ids = array_values(array_filter(array_column($coupon_list, 'goods_id')));
                $category_ids = array_values(array_filter(array_column($coupon_list, 'category_id')));
                if (!empty($goods_ids)) {
                    $sku_where[] = [ 'goods.goods_id', 'in', $goods_ids ];
                } elseif (!empty($category_ids)) {
                    $like_arr = [];
                    foreach ($category_ids as $k => $v) {
                        $like_arr[] = "%" . $v . "%";
                    }
                    $sku_where[] = [ 'goods_category', "like", $like_arr, 'or' ];
                }
            }
        }

        // 参数过滤
        if (!empty($where[ 'order' ]) && in_array($where[ 'order' ], [ 'sale_num', 'price' ])) {
            $order = $where[ 'order' ] . ' ' . ($where[ 'sort' ] === 'asc' ? 'asc' : 'desc');
        } elseif (($where[ 'order' ] ?? '') === 'latest') {
            $order = 'goods.goods_id desc';
        } else {
            $sort_config = ( new CoreGoodsConfigService() )->getSortConfig($this->site_id);
            if ($sort_config[ 'sort_column' ] == 'sale_price') {
                $sort_config[ 'sort_column' ] = 'goodsSku.sale_price';
            }
            $order = $sort_config[ 'sort_column' ] . ' ' . $sort_config[ 'sort_type' ];
        }

        $search_model = $this->model
            ->withSearch([ "brand_id", "goods_category", "label_ids", 'service_ids' ], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => [ 'sku_id', 'sku_name', 'sku_image', 'sku_no', 'goods_id', 'sku_spec_format', 'price', 'market_price', 'sale_price', 'stock', 'weight', 'volume', 'member_price' ]
            ])
            ->where($sku_where);
        $search_model->order($order)->append([ 'goods_cover_thumb_small','goods_cover_thumb_mid', 'goods_label_name', 'goods_brand', 'sale_state', 'warranty_expire_date' ]);
        $this->applyDeviceRangeFilters($search_model, $where);
        $list = $this->pageQuery($search_model);
        $goods_active_price_service = ( new CoreGoodsActivePriceService() );
        foreach ($list[ 'data' ] as $k => &$v) {
            if (!empty($v[ 'goodsSku' ])) {
                $v[ 'goodsSku' ][ 'member_discount' ] = $v[ 'member_discount' ];
                $list_show_price = $goods_active_price_service->getShowPrice($v[ 'goodsSku' ], $this->site_id, $this->member_id);
                $v[ 'goodsSku' ][ 'show_price' ] = $list_show_price[ 'show_price' ];
                $v[ 'goodsSku' ][ 'show_type' ] = $list_show_price[ 'show_type' ];
            }
            // 限购查询当前会员已购数量
            $has_buy = ( new CoreGoodsLimitBuyService() )->getGoodsHasBuyNumber($this->site_id, $this->member_id, $v[ 'goods_id' ]);
            $v[ 'has_buy' ] = $has_buy;
            $v['source_type'] = $is_agent_site && (string)($v['source'] ?? '') === (string)$master_site_id
                ? 'proxy'
                : 'self';
        }
        return $list;
    }

    /**
     * 将前台来源选择翻译为真实数据库条件。
     * source=1 是“本站自营”的业务枚举，不是数据库中 source 字段必须等于 1。
     */
    private function applySourceSelector(array &$skuWhere, string $selector, int $masterSiteId): void
    {
        $selector = strtolower(trim($selector));
        if (in_array($selector, ['1', 'self', 'local'], true)) {
            $siteId = (int)$this->site_id;
            // 历史自营数据存在空值、字符串0和本站ID三种写法。
            $skuWhere[] = ['goods.source', 'in', ['', '0', (string)$siteId]];
            return;
        }
        if (in_array($selector, ['agent', 'proxy'], true)) $selector = (string)$masterSiteId;
        if (ctype_digit($selector) && (int)$selector > 0) {
            $skuWhere[] = ['goods.source', '=', (string)(int)$selector];
        }
    }

    private function normalizeMultiValue($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($item) => trim((string)$item), $value), static fn($item) => $item !== ''));
        }
        if ($value === null || trim((string)$value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', (string)$value)), static fn($item) => $item !== ''));
    }

    /**
     * 返回列表页实际可消费的筛选项。
     * 筛选数据全部按站点隔离；内存优先取当前在售商品的真实值，避免展示空选项。
     */
    public function getFilterOptions(array $where = []): array
    {
        $category_service = new GoodsCategoryService();
        $categories = $category_service->getTree();

        $memoryQuery = (new Goods())
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'status', '=', 1 ],
                [ 'sale_status', '=', 'available' ],
                [ 'delete_time', '=', 0 ],
                [ 'memory_group', '<>', '' ],
            ]);
        $optionCategoryIds = $this->normalizeMultiValue($where['goods_category'] ?? '');
        if ($optionCategoryIds) {
            $memoryQuery->where(function ($query) use ($optionCategoryIds) {
                foreach ($optionCategoryIds as $index => $categoryId) {
                    $method = $index === 0 ? 'where' : 'whereOr';
                    $query->$method('goods_category', 'like', '%"' . (int)$categoryId . '"%');
                }
            });
        }
        $optionBrandIds = $this->normalizeMultiValue($where['brand_id'] ?? '');
        if ($optionBrandIds) $memoryQuery->whereIn('brand_id', array_map('intval', $optionBrandIds));
        $optionGrades = $this->normalizeMultiValue($where['condition_grade'] ?? '');
        if ($optionGrades) $memoryQuery->whereIn('condition_grade', $optionGrades);
        $optionColors = $this->normalizeMultiValue($where['device_color'] ?? '');
        if ($optionColors) $memoryQuery->whereIn('device_color', $optionColors);
        $memories = $memoryQuery
            ->field('memory_group')
            ->group('memory_group')
            ->orderRaw('LENGTH(memory_group) asc, memory_group asc')
            ->column('memory_group');

        // 颜色只返回当前分类/型号范围内真实存在的值，避免苹果型号展示安卓颜色等无效噪音。
        $colorQuery = (new Goods())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['sale_status', '=', 'available'],
            ['delete_time', '=', 0],
            ['device_color', '<>', ''],
        ]);
        $categoryIds = $optionCategoryIds;
        if ($categoryIds) {
            $colorQuery->where(function ($query) use ($categoryIds) {
                foreach ($categoryIds as $index => $categoryId) {
                    $method = $index === 0 ? 'where' : 'whereOr';
                    $query->$method('goods_category', 'like', '%"' . (int)$categoryId . '"%');
                }
            });
        }
        $brandIds = $optionBrandIds;
        if ($brandIds) $colorQuery->whereIn('brand_id', array_map('intval', $brandIds));
        $optionMemories = $this->normalizeMultiValue($where['memory_group'] ?? '');
        if ($optionMemories) $colorQuery->whereIn('memory_group', $optionMemories);
        if ($optionGrades) $colorQuery->whereIn('condition_grade', $optionGrades);
        $colors = $colorQuery->field('device_color')->group('device_color')->order('device_color asc')->column('device_color');
        $deviceAttributes = new CoreDeviceAttributeService();

        $grades = (new GoodsGrade())
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'status', '=', 1 ],
            ])
            ->field('grade_id,grade_name,grade_desc,grade_image,sort')
            ->order('sort desc,grade_id asc')
            ->select()
            ->toArray();

        if (empty($grades)) {
            $grade_names = (new Goods())
                ->where([
                    [ 'site_id', '=', $this->site_id ],
                    [ 'status', '=', 1 ],
                    [ 'delete_time', '=', 0 ],
                    [ 'condition_grade', '<>', '' ],
                ])
                ->field('condition_grade')
                ->group('condition_grade')
                ->order('condition_grade asc')
                ->column('condition_grade');
            $grades = array_map(static fn($name) => [
                'grade_id' => (string)$name,
                'grade_name' => (string)$name,
                'grade_desc' => '',
                'grade_image' => '',
                'sort' => 0,
            ], $grade_names);
        }

        // 选中分类后仅返回该范围内真实存在的成色，避免型号页出现无法命中的全局选项。
        if ($optionCategoryIds && !empty($grades)) {
            $availableGradeQuery = (new Goods())->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
                ['sale_status', '=', 'available'],
                ['delete_time', '=', 0],
                ['condition_grade', '<>', ''],
            ]);
            $availableGradeQuery->where(function ($query) use ($optionCategoryIds) {
                foreach ($optionCategoryIds as $index => $categoryId) {
                    $method = $index === 0 ? 'where' : 'whereOr';
                    $query->$method('goods_category', 'like', '%"' . (int)$categoryId . '"%');
                }
            });
            $availableGradeNames = $availableGradeQuery
                ->field('condition_grade')
                ->group('condition_grade')
                ->column('condition_grade');
            $availableGradeMap = array_fill_keys(array_map('strval', $availableGradeNames), true);
            $grades = array_values(array_filter($grades, static fn(array $grade) => isset($availableGradeMap[(string)($grade['grade_name'] ?? '')])));
        }

        $label_groups = (new LabelGroup())
            ->where('site_id', $this->site_id)
            ->field('group_id,group_name,sort')
            ->order('sort desc,group_id asc')
            ->select()
            ->toArray();
        $labels = (new Label())
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'status', '=', 1 ],
            ])
            ->field('label_id,label_name,group_id,style_type,color_json,icon,sort')
            ->order('sort desc,label_id asc')
            ->select()
            ->toArray();
        $label_map = [];
        foreach ($labels as $label) {
            $label_map[(int)$label['group_id']][] = $label;
        }
        foreach ($label_groups as &$group) {
            $group['items'] = $label_map[(int)$group['group_id']] ?? [];
        }
        unset($group);
        if (empty($label_groups) && !empty($labels)) {
            $label_groups[] = [ 'group_id' => 0, 'group_name' => '商品标签', 'sort' => 0, 'items' => $labels ];
        }

        $services = (new Service())
            ->where('site_id', $this->site_id)
            ->field('service_id,service_name,image,desc')
            ->order('service_id asc')
            ->select()
            ->toArray();
        $brands = (new Brand())
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'delete_time', '=', 0 ],
            ])
            ->field('brand_id,brand_name,logo,sort')
            ->order('sort desc,brand_id asc')
            ->select()
            ->toArray();

        return [
            'categories' => $categories,
            'memories' => array_values(array_map(static fn($name) => [ 'value' => (string)$name, 'label' => (string)$name ], $memories)),
            'grades' => array_values($grades),
            'colors' => array_values(array_map(static fn($name) => ['value' => (string)$name, 'label' => (string)$name], $colors)),
            'battery_ranges' => $deviceAttributes->batteryRanges(),
            'warranty_ranges' => array_map(static function (array $item) {
                unset($item['min'], $item['max']);
                return $item;
            }, $deviceAttributes->warrantyRanges()),
            'label_groups' => array_values(array_filter($label_groups, static fn($group) => !empty($group['items']))),
            'services' => $services,
            'brands' => $brands,
            'warehouses' => $this->getWarehouseOptions(),
            'price_ranges' => [
                [ 'label' => '500元以下', 'min' => '', 'max' => 500 ],
                [ 'label' => '500-1000元', 'min' => 500, 'max' => 1000 ],
                [ 'label' => '1000-2000元', 'min' => 1000, 'max' => 2000 ],
                [ 'label' => '2000-4000元', 'min' => 2000, 'max' => 4000 ],
                [ 'label' => '4000元以上', 'min' => 4000, 'max' => '' ],
            ],
        ];
    }

    private function applyDeviceRangeFilters($query, array $where): void
    {
        $attributeService = new CoreDeviceAttributeService();
        $this->applyNamedRanges($query, 'goods.battery_health', $where['battery_range'] ?? '', $attributeService->batteryRanges());
        $this->applyNamedRanges($query, 'goods.warranty_expire_time', $where['warranty_range'] ?? '', $attributeService->warrantyRanges());
    }

    private function applyNamedRanges($query, string $field, $selected, array $ranges): void
    {
        $selected = $this->normalizeMultiValue($selected);
        if (!$selected) return;
        $rangeMap = [];
        foreach ($ranges as $range) $rangeMap[(string)$range['value']] = $range;
        $valid = array_values(array_filter(array_map(static fn($value) => $rangeMap[$value] ?? null, $selected)));
        if (!$valid) return;
        $query->where(function ($group) use ($field, $valid) {
            foreach ($valid as $index => $range) {
                $method = $index === 0 ? 'where' : 'whereOr';
                $group->$method(function ($item) use ($field, $range) {
                    if ($range['max'] === null) {
                        $item->where($field, '>=', (int)$range['min']);
                    } else {
                        $item->where($field, 'between', [(int)$range['min'], (int)$range['max']]);
                    }
                });
            }
        });
    }

    /**
     * 用户端仓库切换选项：本地仓(自营) / 代理仓(主站货,如石家庄仓)。
     * show=1 时前端才显示切换；名称优先使用当前子站的展示配置。
     * @return array
     */
    public function getWarehouseOptions(): array
    {
        $displayConfig = (new \addon\phone_shop\app\service\core\agent\AgentConfigService())->getDisplayConfig((int)$this->site_id);
        $master_site_id = $displayConfig['master_site_id'];
        $is_master = ($this->site_id == $master_site_id);
        // 本站是否存在代理货(source=主站、在售) -> 决定是否显示切换
        $has_agent_goods = ( new Goods() )->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'source', '=', (string) $master_site_id ],
            [ 'status', '=', 1 ],
        ])->count() > 0;
        return [
            'show'           => (!$is_master && $has_agent_goods) ? 1 : 0,
            'local_name'     => '本地仓',
            'agent_name'     => $displayConfig['effective_agent_name'],
            'master_site_id' => $master_site_id,
        ];
    }

    /**
     * 获取商品详情
     * @param array $data
     * @return array
     */
    public function getDetail(array $data)
    {
        $sku_id = $data[ 'sku_id' ];
        $goods_id = $data[ 'goods_id' ];
        if (!empty($goods_id)) {
            $goods_info = ( new Goods() )->where([ [ 'site_id', '=', $this->site_id ], [ 'goods_id', '=', $goods_id ], [ 'delete_time', '=', 0 ] ])->count();
            if (empty($goods_info)) throw new CommonException('SHOP_GOODS_NOT_EXIST');//商品不存在
        }

        $goods_sku_model = new GoodsSku();

        if (empty($sku_id) && !empty($goods_id)) {
            // 查询默认规格项
            $default_sku_info = $goods_sku_model->where([ [ 'goods_id', '=', $goods_id ], [ 'site_id', '=', $this->site_id ], [ 'is_default', '=', 1 ] ], 'sku_id')
                ->field('sku_id')->findOrEmpty()->toArray();
            if (!empty($default_sku_info)) {
                $sku_id = $default_sku_info[ 'sku_id' ];
            }
        }

        $field = 'sku_id, sku_name, sku_image, sku_no, goods_id, site_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, sale_num, is_default,member_price';

        $info = $goods_sku_model->where([ [ 'sku_id', '=', $sku_id ], [ 'site_id', '=', $this->site_id ] ])
            ->field($field)
            ->with([
                'goods' => function ($query) {
                    $query->withField('goods_id, goods_image_width,goods_image_height, site_id, goods_name,goods_type, sub_title, goods_cover, goods_category, goods_image,goods_video,goods_desc,brand_id,label_ids,service_ids, unit, stock, sale_num + virtual_sale_num as sale_num, is_limit,limit_type,max_buy,min_buy,status,sale_status,is_online_sellable,delivery_type,attr_ids,attr_format,member_discount,is_discount,poster_id,virtual_receive_type,is_gift,form_id,diy_detail_id,qc_report,memory_group,condition_grade,device_color,battery_health,warranty_expire_time')
                        ->append([ 'goods_type_name', 'goods_cover_thumb_mid', 'delivery_type_list', 'goods_image_thumb_small', 'goods_image_thumb_mid', 'goods_image_thumb_big', 'goods_brand', 'sale_state', 'warranty_expire_date' ]);
                },
                // 商品规格列表
                'skuList' => function ($query) {
                    $query->field('sku_id, site_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, is_default,member_price');
                },
                // 商品规格项/规格值列表
                'goodsSpec' => function ($query) {
                    $query->field('spec_id, goods_id, spec_name, spec_values');
                },
            ])
            ->append([ 'sku_image_thumb_small', 'sku_image_thumb_mid', 'sku_image_thumb_big' ])
            ->findOrEmpty()->toArray();
        if (!empty($info) && !empty($info[ 'goods' ])) {
            // 成色等级在商品表中保存的是名称快照，详情页仍需要消费等级中心维护的
            // 图片与说明。按站点和名称读取，避免把后台等级表的主键耦合进商品数据。
            $grade_name = trim((string)($info['goods']['condition_grade'] ?? ''));
            if ($grade_name !== '') {
                $grade_info = (new GoodsGrade())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['grade_name', '=', $grade_name]
                    ])
                    ->field('grade_id,grade_name,grade_desc,grade_image')
                    ->findOrEmpty()
                    ->toArray();

                $info['goods']['condition_grade_info'] = !empty($grade_info) ? $grade_info : [
                    'grade_id' => 0,
                    'grade_name' => $grade_name,
                    'grade_desc' => '',
                    'grade_image' => ''
                ];
            }

            // 历史版本曾把结构化质检 JSON 写进 goods_desc。接口层兜底清洗，
            // 质检数据仍通过 qc_report 交给独立质检组件展示。
            $info['goods']['goods_desc'] = (new CoreGoodsDescriptionService())
                ->sanitize((string)($info['goods']['goods_desc'] ?? ''));
            $info[ 'type' ] = $data[ 'type' ] ?? '';

            $goods_active_price_service = ( new CoreGoodsActivePriceService() );
            //获取展示信息（价格 标签）
            $info[ 'member_discount' ] = $info[ 'goods' ][ 'member_discount' ] ?? '';
            $detail_show_price = $goods_active_price_service->getShowPrice($info, $this->site_id, $this->member_id);
            $info[ 'show_price' ] = $detail_show_price[ 'show_price' ];
            $info[ 'show_type' ] = $detail_show_price[ 'show_type' ];
            //组装数据
            if (!empty($info[ 'skuList' ])) {
                foreach ($info[ 'skuList' ] as &$value) {
                    $value[ 'type' ] = $data[ 'type' ] ?? '';
                    $value[ 'member_discount' ] = $info[ 'goods' ][ 'member_discount' ] ?? '';
                    $list_show_price = $goods_active_price_service->getShowPrice($value, $this->site_id, $this->member_id);
                    $value[ 'show_price' ] = $list_show_price[ 'show_price' ];
                    $value[ 'show_type' ] = $list_show_price[ 'show_type' ];
                }
            }

            if (!empty($info[ 'goods' ][ 'service_ids' ])) {
                // 商品服务
                $info[ 'service' ] = $this->getGoodsService($info[ 'goods' ][ 'service_ids' ]);
            }
            if (!empty($info[ 'goods' ][ 'label_ids' ])) {
                // 商品标签
                $info[ 'label_info' ] = $this->getGoodsLabel($info[ 'goods' ][ 'label_ids' ]);
            }
//            if ($info[ 'show_type' ] == GoodsDict::DISCOUNT_PRICE) {
            // 参与限时折扣，查询活动信息
            if ($info[ 'goods' ][ 'is_discount' ] == 1) {
                $discount_service = new DiscountService();
                $info[ 'discount_info' ] = $discount_service->getInfoByGoods($info[ 'goods_id' ]);
                if (!empty($info[ 'discount_info' ])) {
                    $info[ 'discount_info' ][ 'active' ][ 'start_time' ] = strtotime($info[ 'discount_info' ][ 'active' ][ 'start_time' ]);
                    $info[ 'discount_info' ][ 'active' ][ 'end_time' ] = strtotime($info[ 'discount_info' ][ 'active' ][ 'end_time' ]);
                    $info[ 'type' ] = ActiveDict::DISCOUNT;
                }
            } else {
                $info[ 'type' ] = '';
                $info[ 'type_name' ] = '';
            }
//            }

            //新人价
            if (!empty($data[ 'type' ]) && $data[ 'type' ] == ActiveDict::NEWCOMER_DISCOUNT) {
                // 查询新人价
                $newcomer_service = new NewcomerService();
                if ($newcomer_service->checkIfNewcomer()) {
                    $newcomer_info = $newcomer_service->getNewcomerInfo($info[ 'goods_id' ], $info[ 'sku_id' ]);
                    if (!empty($newcomer_info)) {
                        $info[ 'newcomer_price' ] = $newcomer_info[ 'newcomer_price' ];
                        $info[ 'newcomer_desc' ] = $newcomer_info[ 'newcomer_desc' ];
                        $info[ 'is_newcomer' ] = 1;
                        $info[ 'type' ] = ActiveDict::NEWCOMER_DISCOUNT;
                    }
                    $newcomer_service->getNewcomerPriceByList($info[ 'skuList' ]);
                } else {
                    $info[ 'is_newcomer' ] = 0;
                    $info[ 'type' ] = '';
                    $info[ 'type_name' ] = '';
                }
            }

            if (!empty($this->member_id)) {
                $info[ 'goods' ][ 'is_collect' ] = $this->getGoodsIsCollect($info[ 'goods_id' ]);
                // 限购查询当前会员已购数量
                $has_buy = ( new CoreGoodsLimitBuyService() )->getGoodsHasBuyNumber($this->site_id, $this->member_id, $info[ 'goods' ][ 'goods_id' ]);
                $info[ 'goods' ][ 'has_buy' ] = $has_buy;
            } else {
                $info[ 'goods' ][ 'has_buy' ] = 0;
                $info[ 'goods' ][ 'is_collect' ] = 0;
            }

            $info[ 'type_name' ] = '';
            if (!empty($info[ 'type' ])) {
                $info[ 'type_name' ] = ActiveDict::getClass($info[ 'type' ]); // 查询来源活动类型
            }

            //查询评价设置是否显示
            $core_order_config_service = new CoreOrderConfigService();
            $evaluate_config = $core_order_config_service->getEvaluateConfig($this->site_id);
            $info[ 'evaluate_is_show' ] = $evaluate_config[ 'evaluate_is_show' ];

            // 商品统计-浏览次数
            CoreGoodsStatService::addStat([ 'site_id' => $this->site_id, 'goods_id' => $info[ 'goods' ][ 'goods_id' ], 'access_num' => 1 ]);
            ( new CoreGoodsAccessNumService() )->inc([ 'goods_id' => $info[ 'goods' ][ 'goods_id' ], 'access_num' => 1 ]);

            // 种草秀数据查询
            $info[ 'sow_show_list' ] = event('SowShowData', [ 'relate_id' => $info[ 'goods_id' ], 'relate_type' => 'phone_shop', 'limit' => 3 ])[ 0 ] ?? [];

            //自定义详情模版
            $info[ 'diy_detail_info' ] = ( new DiyService() )->getInfo([ 'id' => $info[ 'goods' ][ 'diy_detail_id' ], 'name' => 'DIY_PHONE_SHOP_GOODS_DETAIL' ]);

            //获取商品主图第一张图片宽高
            $first_goods_image = explode(',',$info['goods']['goods_image'])[0] ?? '';
            $info['goods']['image_size'] = ['width' => $info['goods']['goods_image_width'] ?? 800, 'height' => $info['goods']['goods_image_height'] ?? 800];
        }

        return $info;
    }

    public function getGoodsService($service_ids) : array
    {
        $service = ( new Service() )
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'service_id', 'in', $service_ids ]
            ])
            ->field('service_id, service_name, image, desc')
            ->select()
            ->toArray();
        return $service;
    }


    public function getGoodsLabel($label_ids) : array
    {
        $goods_label_model = new Label();
        $label_info = $goods_label_model
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'label_id', 'in', $label_ids ],
                [ 'status', '=', 1 ]
            ])
            ->field('label_id, label_name, memo,style_type,color_json,icon')
            ->order('sort desc,label_id desc')
            ->select()
            ->toArray();
        return $label_info;
    }

    public function getGoodsIsCollect($goods_id) : int
    {
        $goods_collect_model = new GoodsCollect();
        $collect_info = $goods_collect_model
            ->where([ [ 'site_id', '=', $this->site_id ], [ 'member_id', '=', $this->member_id ], [ 'goods_id', '=', $goods_id ] ])
            ->findOrEmpty()
            ->toArray();
        if (!empty($collect_info)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 获取商品规格信息，切换规格
     * @param int $sku_id
     * @return array
     */
    public function getSku(int $sku_id)
    {
        $field = 'site_id,sku_id, sku_name, sku_image, goods_id, sku_spec_format, price, market_price, sale_price, stock, member_price';

        $goods_sku_model = new GoodsSku();

        $info = $goods_sku_model->where([ [ 'site_id', '=', $this->site_id ], [ 'sku_id', '=', $sku_id ] ])
            ->field($field)
            ->with([
                // 商品主表
                'goodsSingle' => function ($query) {
                    $query->withField('site_id,goods_id, goods_name, goods_cover, unit, member_discount,is_discount')
                        ->append([ 'goods_cover_thumb_mid' ]);
                },
                // 商品规格列表
                'skuList' => function ($query) {
                    $query->field('site_id,sku_id, sku_name, sku_image, goods_id, sku_spec_format, price, market_price, sale_price, stock,member_price')
                        ->append([ 'sku_image_thumb_mid' ]);
                },
                // 商品规格项/规格值列表
                'goodsSpec' => function ($query) {
                    $query->field('spec_id, goods_id, spec_name, spec_values');
                },
            ])
            ->append([ 'sku_image_thumb_mid' ])
            ->findOrEmpty()->toArray();


        if (!empty($info)) {
            $info[ 'goods' ] = $info[ 'goodsSingle' ];
            unset($info[ 'goodsSingle' ]);
            if (!empty($this->member_id)) {
                // 限购查询当前会员已购数量
                $has_buy = ( new CoreGoodsLimitBuyService() )->getGoodsHasBuyNumber($this->site_id, $this->member_id, $info[ 'goods_id' ]);
                $info[ 'has_buy' ] = $has_buy;
            }
            $goods_active_price_service = new CoreGoodsActivePriceService();
            //获取展示信息（价格 标签）
            $info[ 'member_discount' ] = $info[ 'goods' ][ 'member_discount' ] ?? '';
            $detail_show_price = $goods_active_price_service->getShowPrice($info, $this->site_id, $this->member_id);
            $info[ 'show_price' ] = $detail_show_price[ 'show_price' ];
            $info[ 'show_type' ] = $detail_show_price[ 'show_type' ];
            //组装数据
            if (!empty($info[ 'skuList' ])) {
                foreach ($info[ 'skuList' ] as &$value) {
                    $value[ 'member_discount' ] = $info[ 'goods' ][ 'member_discount' ] ?? '';
                    $list_show_price = $goods_active_price_service->getShowPrice($value, $this->site_id, $this->member_id);
                    $value[ 'show_price' ] = $list_show_price[ 'show_price' ];
                    $value[ 'show_type' ] = $list_show_price[ 'show_type' ];
                }
            }
        }

        return $info;
    }

    /**
     * 获取商品列表供组件调用
     * @param array $where
     * @return array
     */
    public function getGoodsComponents(array $where = [])
    {
        $field = 'goods_id,site_id,goods_name,sub_title,goods_type,goods_cover,unit,sale_num + goods.virtual_sale_num as sale_num,member_discount,label_ids,brand_id,is_limit,limit_type,max_buy,min_buy,virtual_receive_type';

        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goodsSku.site_id', '=', $this->site_id ],
            [ 'goods.is_gift', '=', GoodsDict::NOT_IS_GIFT ],
            [ 'status', '=', 1 ]
        ];

        if (!empty($where[ 'goods_ids' ])) {
            $sku_where[] = [ 'goods.goods_id', 'in', $where[ 'goods_ids' ] ];
        }

        // 参数过滤
        if (!empty($where[ 'order' ]) && in_array($where[ 'order' ], [ 'sale_num', 'price' ])) {
            $order = $where[ 'order' ] . ' desc';
        } else {
            $sort_config = ( new CoreGoodsConfigService() )->getSortConfig($this->site_id);
            if ($sort_config[ 'sort_column' ] == 'sale_price') {
                $sort_config[ 'sort_column' ] = 'goodsSku.sale_price';
            }
            $order = $sort_config[ 'sort_column' ] . ' ' . $sort_config[ 'sort_type' ];
        }

        $list = $this->model
            ->withSearch([ "goods_category", "label_ids", 'service_ids' ], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => [ 'sku_id', 'sku_name', 'sku_image', 'sku_no', 'goods_id', 'sku_spec_format', 'price', 'market_price', 'sale_price', 'stock', 'weight', 'volume', 'member_price' ]
            ])
            ->where($sku_where)->order($order)->append([ 'goods_cover_thumb_small','goods_cover_thumb_mid', 'goods_label_name', 'goods_brand' ])
            ->limit($where[ 'num' ])
            ->select()->toArray();
        $goods_active_price_service = ( new CoreGoodsActivePriceService() );
        foreach ($list as $k => &$v) {
            if (!empty($v[ 'goodsSku' ])) {
                $v[ 'goodsSku' ][ 'member_discount' ] = $v[ 'member_discount' ];
                $list_show_price = $goods_active_price_service->getShowPrice($v[ 'goodsSku' ], $this->site_id, $this->member_id);
                $v[ 'goodsSku' ][ 'show_price' ] = $list_show_price[ 'show_price' ];
                $v[ 'goodsSku' ][ 'show_type' ] = $list_show_price[ 'show_type' ];
            }
        }
        return $list;
    }

    public function getMemberInfo()
    {
        $member_model = new Member();
        $member_field = 'member_level';
        $member_info = $member_model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ]
        ])->field($member_field)
            ->with([
                // 会员等级
                'memberLevelData' => function ($query) {
                    $query->field('level_id, site_id, level_no, level_name, status, level_benefits, level_gifts');
                },
            ])
            ->findOrEmpty()->toArray();
        return $member_info;
    }

    /**
     * 查询商品的会员价
     * @param $member_info
     * @param string $member_discount 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
     * @param string $member_price 会员价，json格式，指定会员价，数据结构为：{"level_12":"92.00","level_13":"72.00","level_14":"66.00","level_15":"45.00"}
     * @param $price
     * @return int|string
     */
    /**
     * 从 member_price JSON 取该会员等级的指定价。键统一口径=站内序号 level_no(优先),兼容旧 level_id。取不到返回 null。
     */
    private function pickLevelPrice($member_price_json, $member_info)
    {
        return CoreMemberPriceService::pickLevelPrice($member_price_json, (array)$member_info);
    }

    public function getMemberPrice($member_info, $member_discount, $member_price, $price)
    {
        if (empty($member_discount)) {
            return $price;
        }

        // 未找到会员，排除
        if (empty($member_info)) {
            return $price;
        }

        // 没有会员等级，排除
        if (!empty($member_info) && empty($member_info[ 'member_level' ])) {
            return $price;
        }

        if ($member_discount == 'discount') {
            // 按照会员等级折扣计算

            // 默认按会员享受折扣计算
            if (!empty($member_info[ 'memberLevelData' ][ 'level_benefits' ])
                && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ])
                && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ][ 'is_use' ])) {

                $price = CoreMemberDiscountPriceService::calculate(
                    $price,
                    $member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ]
                );
            }

        } elseif ($member_discount == 'fixed_price') {
            // 指定会员价(按 level_no 统一口径取,兼容旧 level_id)
            if (!empty($member_price)) {
                $member_level_price = $this->pickLevelPrice($member_price, $member_info);
                if ($member_level_price !== null) {
                    $price = number_format($member_level_price, 2, '.', '');
                }
            }
        }

        return $price;
    }

    /**
     * 查询商品的会员价
     * @param $member_info
     * @param string $member_discount 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
     * @param $sku_list
     * @return int
     */
    public function getMemberPriceByList($member_info, $member_discount, &$sku_list)
    {

        // 是否按照原价返回
        $is_default = false;

        if (empty($member_discount)) {
            $is_default = true;
        }

        // 未找到会员，排除
        if (empty($member_info)) {
            $is_default = true;
        }

        // 没有会员等级，排除
        if (!empty($member_info) && empty($member_info[ 'member_level' ])) {
            $is_default = true;
        }

        foreach ($sku_list as $k => &$v) {

            if ($is_default) {
                $v[ 'member_price' ] = $v[ 'price' ];
            } else {
                if ($member_discount == 'discount') {
                    // 按照会员等级折扣计算

                    // 默认按会员享受折扣计算
                    if (!empty($member_info[ 'memberLevelData' ][ 'level_benefits' ])
                        && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ])
                        && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ][ 'is_use' ])) {
                        $v[ 'member_price' ] = CoreMemberDiscountPriceService::calculate(
                            $v[ 'price' ],
                            $member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ]
                        );
                    } else {
                        $v[ 'member_price' ] = $v[ 'price' ];
                    }

                } elseif ($member_discount == 'fixed_price') {
                    // 指定会员价(按 level_no 统一口径取,兼容旧 level_id)
                    if (!empty($v[ 'member_price' ])) {
                        $member_level_price = $this->pickLevelPrice($v[ 'member_price' ], $member_info);
                        if ($member_level_price !== null) {
                            $v[ 'member_price' ] = number_format($member_level_price, 2, '.', '');
                        } else {
                            $v[ 'member_price' ] = $v[ 'price' ];
                        }
                    }
                }
            }
        }

        return $sku_list;

    }

}
