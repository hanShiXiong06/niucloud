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

namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\service\core\goods\CoreGoodsChangeLogService;

use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\goods\Brand;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\GoodsSpec;
use addon\phone_shop\app\model\goods\Stat;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\admin\marketing\ManjianService;
use addon\phone_shop\app\service\core\goods\CoreGoodsConfigService;
use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;
use addon\phone_shop\app\service\core\goods\CoreGoodsLimitBuyService;
use addon\phone_shop\app\service\core\goods\CoreMemberDiscountPriceService;
use addon\phone_shop\app\service\core\goods\CoreMemberPriceService;
use addon\phone_shop\app\service\core\goods\CoreGoodsPriceWriteService;
use addon\phone_shop\app\service\core\goods\CoreTierPricingService;
use app\model\diy\Diy;
use app\model\diy_form\DiyForm;
use app\model\member\Member;
use app\service\admin\addon\AddonService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use core\exception\CommonException;
use think\facade\Db;


/**
 * 商品服务层
 * Class GoodsService
 * @package addon\phone_shop\app\service\admin\goods
 */
class GoodsService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Goods();
    }

    /**
     * 获取商品添加/编辑数据
     * @param array $params
     * @return array
     */
    public function getInit(array $params = [])
    {

        $res = [];

        $addon_service = new AddonService();
        $res['addon_shop_supplier'] = $addon_service->getInfoByKey('shop_supplier');

        if (!empty($params['goods_id'])) {
            // 查询商品信息，用于编辑
            $field = 'goods_id,goods_name,sub_title,goods_type,goods_cover,goods_image,goods_video,goods_desc,brand_id,goods_category,memory_group,condition_grade,device_color,battery_health,warranty_expire_time,label_ids,service_ids,unit,stock,virtual_sale_num,is_limit,limit_type,max_buy,min_buy,status,sort,delivery_type,is_free_shipping,fee_type,delivery_money,delivery_template_id,supplier_id,attr_ids,attr_format,qc_report,member_discount,poster_id,is_gift,form_id,diy_detail_id';
            // 历史版本曾把 attr_ids 建成 INT。直接通过 Goods 模型查询时，ORM 会先把
            // JSON 字段交给 json_decode，PHP 8 遇到整数会抛 TypeError，导致商品连编辑页
            // 都无法打开。初始化接口先读取原始字段并在业务层兼容，表结构仍由
            // SchemaSyncService 迁移为 TEXT，避免修改框架 ORM。
            $goods_info = Db::name('phone_shop_goods')
                ->field($field)
                ->where([
                    ['goods_id', '=', (int)$params['goods_id']],
                    ['site_id', '=', $this->site_id],
                    ['delete_time', '=', 0]
                ])
                ->find();
            if (!empty($goods_info)) {

                foreach (['goods_category', 'label_ids', 'service_ids', 'delivery_type', 'attr_ids'] as $json_list_field) {
                    $goods_info[$json_list_field] = $this->decodeGoodsJsonField(
                        $goods_info[$json_list_field] ?? null,
                        true
                    );
                }
                $goods_info['attr_format'] = $this->decodeGoodsJsonField($goods_info['attr_format'] ?? null);

                if (!empty($goods_info['goods_category'])) {
                    $goods_category = array_values($goods_info['goods_category']);
                    $category_service = new CategoryService();
                    $goods_info['goods_category'] = $category_service->checkCategoryValid($goods_category);
                }

                // 商品品牌，处理数据类型
                if (empty($goods_info['brand_id'])) {
                    $goods_info['brand_id'] = '';
                } else {
                    $brand_count = (new Brand())->where([['site_id', '=', $this->site_id], ['brand_id', '=', $goods_info['brand_id']]])->count();
                    if ($brand_count == 0) $goods_info['brand_id'] = '';
                }

                // 供应商，处理数据类型
                if (empty($goods_info['supplier_id'])) {
                    $goods_info['supplier_id'] = '';
                }

                // 标签组
                if (empty($goods_info['label_ids'])) {
                    $goods_info['label_ids'] = [];
                } else {
                    $goods_info['label_ids'] = array_map(function ($item) {
                        return (int)$item;
                    }, $goods_info['label_ids']);
                }

                // 商品服务
                if (empty($goods_info['service_ids'])) {
                    $goods_info['service_ids'] = [];
                } else {
                    $goods_info['service_ids'] = array_map(function ($item) {
                        return (int)$item;
                    }, $goods_info['service_ids']);
                }

                // 商品参数，处理数据类型
                if (empty($goods_info['attr_ids'])) {
                    $goods_info['attr_ids'] = [];
                } else {
                    $goods_info['attr_ids'] = array_map(function ($item) {
                        return (int)$item;
                    }, $goods_info['attr_ids']);
                }

                // 商品海报id，处理数据类型
                if (empty($goods_info['poster_id'])) {
                    $goods_info['poster_id'] = '';
                }

                // 自定义商品详情模板id，处理数据类型
                if (!empty($goods_info['diy_detail_id'])) {
                    $diy_model = new Diy();
                    $diy_count = $diy_model->where([
                        ['site_id', '=', $this->site_id],
                        ['id', '=', $goods_info['diy_detail_id']]
                    ])->count();
                    if ($diy_count == 0) {
                        $goods_info['diy_detail_id'] = '';
                    }
                } else {
                    $goods_info['diy_detail_id'] = '';
                }

                // 万能表单id，处理数据类型
                if (!empty($goods_info['form_id'])) {
                    $diy_form_model = new DiyForm();
                    $diy_form_count = $diy_form_model->where([
                        ['site_id', '=', $this->site_id],
                        ['form_id', '=', $goods_info['form_id']]
                    ])->count();
                    if ($diy_form_count == 0) {
                        $goods_info['form_id'] = '';
                    }
                } else {
                    $goods_info['form_id'] = '';
                }

                //  配送方式
                if (empty($goods_info['delivery_type'])) {
                    $goods_info['delivery_type'] = [];
                }

                $goods_info['status'] = (string)$goods_info['status'];

                // 运费模板，处理数据类型
                if (empty($goods_info['delivery_template_id'])) {
                    $goods_info['delivery_template_id'] = '';
                }

                $goods_sku_model = new GoodsSku();

                $sku_field = 'sku_id,sku_name,sku_image,sku_no,goods_id,sku_spec_format,price,market_price,cost_price,stock,weight,volume,is_default,member_price,device_snapshot';
                $sku_order = 'sku_id asc';
                $goods_info['sku_list'] = $goods_sku_model->withSearch(["goods_id"], ['goods_id' => $params['goods_id']])->field($sku_field)->order($sku_order)->select()->toArray();
                $pricingPolicy = (new CoreTierPricingService())->policy((int)$this->site_id);
                foreach ($goods_info['sku_list'] as &$pricingSku) {
                    $pricingSku['pricing_base_price'] = CoreTierPricingService::baseFromSku($pricingSku, $pricingPolicy);
                    unset($pricingSku['device_snapshot']);
                }
                unset($pricingSku);

                $temp_sku_data = array_filter(array_column($goods_info['sku_list'], 'sku_spec_format'));
                $goods_info['spec_type'] = 'single';
                if (!empty($temp_sku_data)) {
                    // 多规格
                    $goods_info['spec_type'] = 'multi';

                    $goods_spec_model = new GoodsSpec();
                    $spec_field = 'spec_id,goods_id,spec_name,spec_values';
                    $spec_order = 'spec_id asc';
                    $goods_info['spec_list'] = $goods_spec_model->withSearch(["goods_id"], ['goods_id' => $params['goods_id']])->field($spec_field)->order($spec_order)->select()->toArray();

                }

                // 查询商品参与营销活动的数量
                $goods_info['active_goods_count'] = $this->getActiveGoodsCount($goods_info['goods_id']);

                $res['goods_info'] = $goods_info;
            }

        }
        $res['default_sort'] = (new CoreGoodsConfigService())->getDefaultSort($this->site_id);

        return $res;
    }

    /**
     * 安全解析商品历史 JSON 字段。
     *
     * 列表字段兼容早期的整数、逗号分隔值及 JSON 数组；对象字段只接受数组，
     * 无法识别的数据按空值返回，由用户重新编辑保存后写回标准 JSON。
     */
    private function decodeGoodsJsonField($value, bool $acceptScalar = false): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null) {
            return [];
        }

        $text = trim((string)$value);
        if ($text === '' || $text === '0' || strtolower($text) === 'null') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (!$acceptScalar) {
            return [];
        }

        return array_values(array_filter(
            preg_split('/[,，\\s]+/', $text) ?: [],
            static fn($item) => trim((string)$item) !== '' && trim((string)$item) !== '0'
        ));
    }

    /**
     * 获取商品列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'goods_id,site_id,source,source_goods_id,is_proxy,goods_no,goods_name,sub_title,goods_type,goods_cover,stock,sale_num,status,sale_status,is_online_sellable,memory_group,condition_grade,device_color,battery_health,warranty_expire_time,sort,create_time,update_time,member_discount,is_gift';
        $order = 'create_time desc';
        $sku_where = [
            ['goodsSku.is_default', '=', 1],
        ];

        if (!empty($where['start_price']) && !empty($where['end_price'])) {
            $money = [$where['start_price'], $where['end_price']];
            sort($money);
            $sku_where[] = ['goodsSku.price', 'between', $money];
        } else if (!empty($where['start_price'])) {
            $sku_where[] = ['goodsSku.price', '>=', $where['start_price']];
        } else if (!empty($where['end_price'])) {
            $sku_where[] = ['goodsSku.price', '<=', $where['end_price']];
        }
        if (!empty($where['order'])) {
            $order = $where['order'] . ' ' . $where['sort'];
        }
        $deviceKeywords = $this->parseDeviceKeywords((string)($where['device_keywords'] ?? ''));
        if (!empty($deviceKeywords)) {
            $skuGoodsIds = (new GoodsSku())->where(function ($query) use ($deviceKeywords) {
                foreach ($deviceKeywords as $index => $keyword) {
                    $method = $index === 0 ? 'where' : 'whereOr';
                    $query->$method(function ($q) use ($keyword) {
                        $q->whereLike('sku_no', '%' . $keyword . '%');
                        if (is_numeric($keyword)) {
                            $q->whereOr('erp_asset_id', '=', (int)$keyword)
                                ->whereOr('sku_id', '=', (int)$keyword);
                        }
                    });
                }
            })->column('goods_id');
            $where['device_goods_ids'] = array_values(array_unique(array_filter(array_map('intval', $skuGoodsIds))));
        }
        $search_model = $this->model->where([['goods.site_id', '=', $this->site_id]])->withSearch(["goods_name", "goods_type", "brand_id", "goods_category", "label_ids", 'service_ids', "sale_num", "status", "memory_group", "condition_grade", "sale_status"], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => ['sku_id', 'goods_id', 'price', 'cost_price', 'member_price', 'erp_asset_id', 'is_unique', 'sku_no']
            ])->where($sku_where)->order($order)->append(['goods_type_name', 'goods_edit_path', 'goods_cover_thumb_small', 'sale_state']);
        $this->applySaleStateFilter($search_model, (string)($where['sale_state'] ?? ''));
        if (isset($where['device_goods_ids'])) {
            $search_model->whereIn('goods.goods_id', !empty($where['device_goods_ids']) ? $where['device_goods_ids'] : [0]);
        }
        // 库龄(天)筛选:库龄 = now - 创建时间。库龄≥X → 创建不晚于 now-X天;库龄≤Y → 创建不早于 now-Y天
        $now = time();
        if (isset($where['start_stock_age']) && $where['start_stock_age'] !== '') {
            $search_model->where('goods.create_time', '<=', $now - (int)$where['start_stock_age'] * 86400);
        }
        if (isset($where['end_stock_age']) && $where['end_stock_age'] !== '') {
            $search_model->where('goods.create_time', '>=', $now - ((int)$where['end_stock_age'] + 1) * 86400);
        }
        if (isset($where['source']) && $where['source'] !== '') {
            $search_model->where('goods.source', '=', (string) $where['source']);
        }
        if (($where['device_color'] ?? '') !== '') {
            $colors = is_array($where['device_color']) ? $where['device_color'] : array_filter(array_map('trim', explode(',', (string)$where['device_color'])));
            if ($colors) $search_model->whereIn('goods.device_color', array_values($colors));
        }
        if (($where['battery_health'] ?? '') !== '') {
            $search_model->where('goods.battery_health', '=', (int)$where['battery_health']);
        }
        if (($where['warranty_expire_time'] ?? '') !== '') {
            $search_model->where('goods.warranty_expire_time', '=', (int)$where['warranty_expire_time']);
        }
        // 自营/代理筛选：仅子站有意义。source==主站id => 代理；否则自营。
        $master_site_id = ( new \addon\phone_shop\app\service\core\agent\AgentConfigService() )->getMasterSiteId();
        $is_master = ($this->site_id == $master_site_id);
        if (empty($where['source']) && !$is_master && !empty($where['proxy_type'])) {
            if ($where['proxy_type'] === 'proxy') {
                $search_model->where('goods.source', '=', (string) $master_site_id);
            } elseif ($where['proxy_type'] === 'self') {
                $search_model->where('goods.source', '<>', (string) $master_site_id);
            }
        }
        $list = $this->pageQuery($search_model);
        $list['data'] = $this->formatGoodsJoinActive($list['data']);
        // 标注每条是否代理货 + 当前站是否主站（前端据此显示/隐藏 自营代理 tab）
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$row) {
                $row['is_proxy_goods'] = (!$is_master && isset($row['source']) && (string) $row['source'] === (string) $master_site_id) ? 1 : 0;
            }
            unset($row);
        }
        $list['is_master_site'] = $is_master ? 1 : 0;
        $list['data'] = (new \addon\phone_shop\app\service\core\goods\CoreGoodsMaterialService())
            ->decorate((int)$this->site_id, $list['data']);
        return $list;
    }

    /**
     * 管理端只暴露一个销售状态筛选，底层仍按事实字段组合查询。
     */
    private function applySaleStateFilter($query, string $state): void
    {
        if ($state === '') {
            return;
        }
        if ($state === 'sold') {
            $query->where('goods.sale_status', '=', 'sold');
            return;
        }
        if ($state === 'locked') {
            $query->where('goods.sale_status', '=', 'locked');
            return;
        }
        if ($state === 'sellable') {
            $query->where([
                ['goods.sale_status', '=', 'available'],
                ['goods.status', '=', 1],
                ['goods.is_online_sellable', '=', 1],
                ['goods.stock', '>', 0],
            ]);
            return;
        }
        if ($state === 'unavailable') {
            $query->where('goods.sale_status', '=', 'available')
                ->where(function ($subQuery) {
                    $subQuery->where('goods.status', '<>', 1)
                        ->whereOr('goods.is_online_sellable', '<>', 1)
                        ->whereOr('goods.stock', '<=', 0);
                });
        }
    }

    private function parseDeviceKeywords(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $parts = preg_split('/[\s,，;；]+/', trim($value));
        $parts = array_filter(array_map(static fn($item) => trim((string)$item), $parts));
        return array_values(array_unique(array_slice($parts, 0, 100)));
    }

    /**
     * 处理商品参与的活动数据
     * @param $goods_data
     * @return mixed
     */
    private function formatGoodsJoinActive($goods_data)
    {
        $goods_ids = array_column($goods_data, 'goods_id');
        $join_list = event('GetGoodsJoinInfo', [
            'goods_ids' => $goods_ids,
            'site_id' => $this->site_id
        ]);
        $goods_join = [];
        foreach ($join_list as $item) {
            if (empty($item)) {
                continue;
            }
            foreach ($item as $goods_id => $value) {
                if (!isset($goods_join[$goods_id])) {
                    {
                        $goods_join[$goods_id] = [];
                    }
                }
                $goods_join[ $goods_id ] = array_merge($goods_join[ $goods_id ], array_values($value));
            }
        }
        if (!empty($goods_join)) {
            $active_tips = [];
            foreach ($goods_join as $goods_id => $join_active) {
                foreach ($join_active as $k => $v) {
                    $join_type = $v[ 'join_type' ];
                    if (!isset($active_tips[ $goods_id ][ $join_type ][ 'name' ])) {
                        $active_tips[ $goods_id ][ $join_type ][ 'name' ] = '';
                    }
                    if (isset($v[ 'short' ])) {
                        $active_tips[ $goods_id ][ $join_type ][ 'short' ] = $v[ 'short' ];
                        if(($v['short']['is_need_params']??0) == 1){
                            $active_tips[ $goods_id ][ $join_type ][ 'jump_url' ] = ($v[ 'short' ]['jump_url'] ?? '')."?active_id=".$join_active[0]['join_id'];
                        }else{
                            $active_tips[ $goods_id ][ $join_type ][ 'jump_url' ] = $v[ 'short' ]['jump_url'] ?? '';
                        }
                        unset($v[ 'short' ]);
                        $active_tips[ $goods_id ][ $join_type ][ 'list' ][] = $v;
                        $active_tips[ $goods_id ][ $join_type ][ 'name' ] .= $v[ 'name' ] . PHP_EOL;
                    } else {
                        // 防止未知数据
                        unset($active_tips[ $goods_id ][ $join_type ]);
                    }
                }
            }
            foreach ($goods_data as &$item) {
                $item[ 'active' ] = $active_tips[ $item[ 'goods_id' ] ] ?? [];
            }
        }
        return $goods_data;
    }

    /**
     * 获取商品信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'goods_id,site_id,goods_name,sub_title,goods_type,goods_cover,goods_image,goods_video,goods_desc,brand_id,goods_category,memory_group,condition_grade,device_color,battery_health,warranty_expire_time,label_ids,service_ids,unit,stock,sale_num,virtual_sale_num,is_limit,limit_type,max_buy,min_buy,status,sort,delivery_type,is_free_shipping,fee_type,delivery_money,delivery_template_id,supplier_id,create_time,update_time,qc_report,member_discount,poster_id,form_id,diy_detail_id';
        $info = $this->model->field($field)->where([ [ 'goods_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加商品
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        try {
            Db::startTrans();
            $deviceAttributeService = new CoreDeviceAttributeService();
            $goods_sku_model = new GoodsSku();
            $goods_spec_model = new GoodsSpec();
            $goods_stat_model = new Stat();

            // 商品封面
            if (!empty($data[ 'goods_image' ])) $data[ 'goods_cover' ] = explode(',', $data[ 'goods_image' ])[ 0 ];

            $goods_data = [
                'site_id' => $this->site_id,
                'goods_name' => $data[ 'goods_name' ],
                'sub_title' => $data[ 'sub_title' ],
                'goods_type' => $data[ 'goods_type' ],
                'goods_cover' => $data[ 'goods_cover' ],
                'goods_image' => $data[ 'goods_image' ],
                'goods_image_width' => $data[ 'goods_image_width' ] ?? 0,
                'goods_image_height' => $data[ 'goods_image_height' ] ?? 0,
                'goods_video' => $data[ 'goods_video' ],
                'goods_category' => array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'goods_category' ]),
                'goods_desc' => $data[ 'goods_desc' ],
                'memory_group' => $data[ 'memory_group' ] ?? '',
                'condition_grade' => $data[ 'condition_grade' ] ?? '',
                'device_color' => $deviceAttributeService->normalizeColor($data['device_color'] ?? ''),
                'battery_health' => $deviceAttributeService->normalizeBattery($data['battery_health'] ?? -1),
                'warranty_expire_time' => $deviceAttributeService->normalizeWarrantyExpire($data['warranty_expire_time'] ?? 0),
                'source' => (string)($data[ 'source' ] ?? ''),
                'qc_report' => is_array($data[ 'qc_report' ] ?? null) ? json_encode($data[ 'qc_report' ], JSON_UNESCAPED_UNICODE) : ($data[ 'qc_report' ] ?? null),
                'brand_id' => $data[ 'brand_id' ],
                'label_ids' => array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'label_ids' ]),
                'service_ids' => array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'service_ids' ]),
                'unit' => $data[ 'unit' ],
                'stock' => $data[ 'stock' ],
                'virtual_sale_num' => $data[ 'virtual_sale_num' ],
                'is_limit' => $data[ 'is_limit' ],
                'limit_type' => $data[ 'limit_type' ],
                'max_buy' => $data[ 'max_buy' ],
                'min_buy' => $data[ 'min_buy' ],
                'is_gift' => $data[ 'is_gift' ],
                'status' => $data[ 'status' ],
                'sort' => $data[ 'sort' ],
                'attr_ids' => $data[ 'attr_ids' ],
                'attr_format' => $data[ 'attr_format' ],
                'delivery_type' => $data[ 'delivery_type' ],
                'is_free_shipping' => $data[ 'is_free_shipping' ],
                'fee_type' => $data[ 'fee_type' ],
                'delivery_money' => $data[ 'delivery_money' ],
                'delivery_template_id' => $data[ 'delivery_template_id' ],
                'supplier_id' => $data[ 'supplier_id' ],
                'member_discount' => $data[ 'member_discount' ],
                'poster_id' => $data[ 'poster_id' ],
                'form_id' => $data[ 'form_id' ],
                'diy_detail_id' => $data[ 'diy_detail_id' ],
                'create_time' => time()
            ];
            $res = $this->model->create($goods_data);

            $sku_data = [];
            if ($data[ 'spec_type' ] == 'single') {
                if (empty($data['_skip_sku_unique_check']) && !empty($data[ 'sku_no' ])) {
                    ( new ConfigService() )->verifySkuNoForSite([ 'sku_no' => $data[ 'sku_no' ] ], (int) $this->site_id);
                }
                // 单规格
                $sku_data = [
                    'site_id' => $this->site_id,
                    'sku_name' => '',
                    'sku_image' => $data[ 'goods_cover' ],
                    'sku_no' => $data[ 'sku_no' ],
                    'goods_id' => $res->goods_id,
                    'sku_spec_format' => '', // sku规格格式
                    'price' => $data[ 'price' ],
                    'market_price' => $data[ 'market_price' ],
                    'sale_price' => $data[ 'price' ],
                    'cost_price' => $data[ 'cost_price' ],
                    'stock' => $data[ 'stock' ],
                    'weight' => $data[ 'weight' ],
                    'volume' => $data[ 'volume' ],
                    'member_price' => $this->encodeMemberPrice($data['member_price'] ?? ''),
                    'is_unique' => (int)($data['is_unique'] ?? 0),
                    'condition_grade' => (string)($data['condition_grade'] ?? ''),
                    'is_default' => 1
                ];
                $goods_sku_model->save($sku_data);

            } elseif ($data[ 'spec_type' ] == 'multi') {
                $sku_no = implode(',', array_column($data[ 'goods_sku_data' ] ?? [], 'sku_no'));
                if (empty($data['_skip_sku_unique_check']) && !empty($sku_no)) {
                    ( new ConfigService() )->verifySkuNoForSite([ 'sku_no' => $sku_no ], (int) $this->site_id);
                }
                // 多规格数据
                $default_spec_count = 0;
                foreach ($data[ 'goods_sku_data' ] as $k => $v) {
                    $sku_spec_format = [];
                    foreach ($v[ 'sku_spec' ] as $ck => $cv) {
                        $sku_spec_format[] = $cv[ 'spec_value_name' ];
                    }
                    $sku_data[] = [
                        'site_id' => $this->site_id,
                        'sku_name' => $v[ 'spec_name' ],
                        'sku_image' => !empty($v[ 'sku_image' ]) ? $v[ 'sku_image' ] : $data[ 'goods_cover' ],
                        'sku_no' => $v[ 'sku_no' ],
                        'goods_id' => $res->goods_id,
                        'sku_spec_format' => implode(',', $sku_spec_format), // sku规格格式
                        'price' => $v[ 'price' ],
                        'market_price' => $v[ 'market_price' ],
                        'sale_price' => $v[ 'price' ],
                        'cost_price' => $v[ 'cost_price' ],
                        'stock' => $v[ 'stock' ],
                        'weight' => $v[ 'weight' ],
                        'volume' => $v[ 'volume' ],
                        'member_price' => $this->encodeMemberPrice($v['member_price'] ?? ''),
                        'is_unique' => (int)($v['is_unique'] ?? 0),
                        'condition_grade' => (string)($v['condition_grade'] ?? ($data['condition_grade'] ?? '')),
                        'is_default' => $v[ 'is_default' ]
                    ];
                    if ($v[ 'is_default' ] == 1) $default_spec_count++;
                }

                if ($default_spec_count == 0) throw new AdminException('SHOP_GOODS_NOT_HAS_DEFAULT_SPEC');

                $goods_sku_model->insertAll($sku_data);

                // 商品规格值
                $spec_data = [];
                foreach ($data[ 'goods_spec_format' ] as $k => $v) {
                    $spec_values = [];
                    foreach ($v[ 'values' ] as $ck => $cv) {
                        $spec_values[] = $cv[ 'spec_value_name' ];
                    }
                    $spec_data[] = [
                        'goods_id' => $res->goods_id,
                        'spec_name' => $v[ 'spec_name' ],
                        'spec_values' => implode(',', $spec_values)
                    ];
                }
                $goods_spec_model->insertAll($spec_data);

            }

            //添加商品统计表数据
            $goods_stat_data = [
                'site_id' => $this->site_id,
                'date' => date('Y-m-d'),
                'date_time' => strtotime(date('Y-m-d')),
                'goods_id' => $res->goods_id,
            ];
            $goods_stat_model->create($goods_stat_data);
            (new CoreGoodsPriceWriteService())->finish((int)$this->site_id, (int)$res->goods_id,
                $data['spec_type'] === 'single' ? [$data] : (array)$data['goods_sku_data'], [], true);
            if (empty($data['_defer_agent_sync'])) (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$res->goods_id, [], 'create');
            Db::commit();

            event('AfterGoodsEdit', [
                'goods_id' => $res->goods_id,
                'goods_data' => $goods_data,
                'sku_data' => $sku_data
            ]);
            event('PhoneShopGoodsSaleableChanged', ['site_id' => $this->site_id, 'goods_id' => $res->goods_id]);

            // 商品跟随以主从 site_id 关系为唯一开关。主站新建商品后自动推送到
            // 所有启用从站，不需要也不允许逐商品建立“关注”关系。
            // ERP 建品还会在 add 之后补 IMEI/质检等字段，可显式延迟到资料补齐后同步。
            if (empty($data['_defer_agent_sync'])) {
                try {
                    $agentConfig = new \addon\phone_shop\app\service\core\agent\AgentConfigService();
                    if ($agentConfig->isMasterSite((int)$this->site_id)) {
                        (new \addon\phone_shop\app\service\core\agent\GoodsDistributionService())
                            ->distribute((int)$res->goods_id);
                    }
                } catch (\Throwable $e) {
                    \think\facade\Log::write('[phone_shop 新商品自动跟随] goods_id=' . (int)$res->goods_id . '失败: ' . $e->getMessage());
                }
            }

            return $res->goods_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 异步任务复用标准商品创建链路。队列没有后台请求上下文，因此显式注入站点，
     * 仍会执行事务、SKU 校验、统计初始化与 AfterGoodsEdit 事件。
     */
    public function addForSite(array $data, int $siteId)
    {
        $oldSiteId = $this->site_id;
        $this->site_id = $siteId;
        try {
            return $this->add($data);
        } finally {
            $this->site_id = $oldSiteId;
        }
    }

    /**
     * 商品编辑
     * @param int $goods_id
     * @param array $data
     * @return bool
     */
    public function edit(int $goods_id, array $data)
    {
        try {
            Db::startTrans();
            $priceBefore = (new CoreGoodsPriceWriteService())->capture((int)$this->site_id, $goods_id);
            $auditBefore = (new CoreGoodsChangeLogService())->capture((int)$this->site_id, $goods_id);
            $deviceAttributeService = new CoreDeviceAttributeService();

            $goods_sku_model = new GoodsSku();
            $goods_spec_model = new GoodsSpec();
            $order_goods_model = new OrderGoods();
            $goods_price = 0;
            // 查询商品参与营销活动的数量
            $active_goods_count = $this->getActiveGoodsCount($goods_id);
            if ($data[ 'status' ] == 0) {
                if ($active_goods_count > 0) {
                    throw new AdminException('SHOP_GOODS_PARTICIPATE_IN_ACTIVE_DISABLED_EDIT');
                }
            }

            // 商品封面
            if (!empty($data[ 'goods_image' ])) $data[ 'goods_cover' ] = explode(',', $data[ 'goods_image' ])[ 0 ];

            $goods_data = [
                'goods_name' => $data[ 'goods_name' ],
                'sub_title' => $data[ 'sub_title' ],
                'goods_type' => $data[ 'goods_type' ],
                'goods_cover' => $data[ 'goods_cover' ],
                'goods_image' => $data[ 'goods_image' ],
                'goods_image_width' => $data[ 'goods_image_width' ] ?? 0,
                'goods_image_height' => $data[ 'goods_image_height' ] ?? 0,
                'goods_video' => $data[ 'goods_video' ],
                'goods_category' => array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'goods_category' ]),
                'goods_desc' => $data[ 'goods_desc' ],
                'memory_group' => $data[ 'memory_group' ] ?? '',
                'condition_grade' => $data[ 'condition_grade' ] ?? '',
                'device_color' => $deviceAttributeService->normalizeColor($data['device_color'] ?? ''),
                'battery_health' => $deviceAttributeService->normalizeBattery($data['battery_health'] ?? -1),
                'warranty_expire_time' => $deviceAttributeService->normalizeWarrantyExpire($data['warranty_expire_time'] ?? 0),
                'qc_report' => is_array($data[ 'qc_report' ] ?? null) ? json_encode($data[ 'qc_report' ], JSON_UNESCAPED_UNICODE) : ($data[ 'qc_report' ] ?? null),
                'brand_id' => $data[ 'brand_id' ],
                'label_ids' => array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'label_ids' ]),
                'service_ids' => array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'service_ids' ]),
                'unit' => $data[ 'unit' ],
                'stock' => $data[ 'stock' ],
                'virtual_sale_num' => $data[ 'virtual_sale_num' ],
                'is_limit' => $data[ 'is_limit' ],
                'limit_type' => $data[ 'limit_type' ],
                'max_buy' => $data[ 'max_buy' ],
                'min_buy' => $data[ 'min_buy' ],
                'is_gift' => $data[ 'is_gift' ],
                'status' => $data[ 'status' ],
                'sort' => $data[ 'sort' ],
                'attr_ids' => $data[ 'attr_ids' ],
                'attr_format' => $data[ 'attr_format' ],
                'delivery_type' => $data[ 'delivery_type' ],
                'is_free_shipping' => $data[ 'is_free_shipping' ],
                'fee_type' => $data[ 'fee_type' ],
                'delivery_money' => $data[ 'delivery_money' ],
                'delivery_template_id' => $data[ 'delivery_template_id' ],
                'supplier_id' => $data[ 'supplier_id' ],
                'member_discount' => $data[ 'member_discount' ],
                'poster_id' => $data[ 'poster_id' ],
                'form_id' => $data[ 'form_id' ],
                'diy_detail_id' => $data[ 'diy_detail_id' ],
                'update_time' => time()
            ];

            $this->model->where([ [ 'goods_id', '=', $goods_id ], [ 'site_id', '=', $this->site_id ] ])->update($goods_data);
            $sku_data = [];
            if ($data[ 'spec_type' ] == 'single') {
                if (!empty($data[ 'sku_no' ])) {
                    $check = [ 'sku_no' => $data[ 'sku_no' ], 'goods_id' => $goods_id ];
                    ( new ConfigService() )->verifySkuNo($check);
                }
                // 单规格
                $sku_data = [
                    'site_id' => $this->site_id,
                    'sku_name' => '',
                    'sku_image' => $data[ 'goods_cover' ],
                    'sku_no' => $data[ 'sku_no' ],
                    'goods_id' => $goods_id,
                    'sku_spec_format' => '', // sku规格格式
                    'market_price' => $data[ 'market_price' ],
                    'cost_price' => $data[ 'cost_price' ],
                    'weight' => $data[ 'weight' ],
                    'volume' => $data[ 'volume' ],
                    'stock' => $data[ 'stock' ],
                    'is_default' => 1
                ];

                // 未参与营销活动，则允许修改 原价、销售价
                if ($active_goods_count == 0) {
                    $sku_data[ 'price' ] = $data[ 'price' ];
                    $sku_data[ 'sale_price' ] = $data[ 'price' ];
                }

                $sku_count = $goods_sku_model->where([ [ 'goods_id', '=', $goods_id ] ])->count();
                if ($sku_count > 1) {

                    // 规格项发生变化，删除旧规格，添加新规格重新生成
                    $goods_sku_model->where([ [ 'goods_id', '=', $goods_id ] ])->delete();

                    // 防止存在遗留规格项，删除旧规格
                    $goods_spec_model->where([ [ 'goods_id', '=', $goods_id ] ])->delete();

                    // 新增规格
                    $goods_sku_model->create($sku_data);

                } else {

                    $goods_sku_model->where([ [ 'goods_id', '=', $goods_id ] ])->update($sku_data);

                    // 防止存在遗留规格项，删除旧规格
                    $goods_spec_model->where([ [ 'goods_id', '=', $goods_id ] ])->delete();
                }
                $goods_price = $sku_data['price'] ?? $goods_sku_model->price;
            } elseif ($data[ 'spec_type' ] == 'multi') {
                $sku_no = implode(',', array_column($data[ 'goods_sku_data' ] ?? [], 'sku_no'));
                if (!empty($sku_no)) {
                    $check = [ 'sku_no' => $sku_no, 'goods_id' => $goods_id ];
                    ( new ConfigService() )->verifySkuNo($check);
                }
                // 多规格数据
                $first_sku_data = reset($data[ 'goods_sku_data' ]);

                // 商品正在参与营销活动，禁止修改规格
                if ($active_goods_count > 0 && empty($first_sku_data[ 'sku_id' ])) {
                    throw new AdminException('SHOP_GOODS_PARTICIPATE_IN_ACTIVE_DISABLED_EDIT');
                }

                // 检测规格项是否发生变化
                if (!empty($first_sku_data[ 'sku_id' ])) {
                    // 规格项没有变化，修改/新增规格数据

                    $sku_id_arr = [];
                    $default_spec_count = 0;
                    foreach ($data[ 'goods_sku_data' ] as $k => $v) {
                        $sku_spec_format = [];
                        foreach ($v[ 'sku_spec' ] as $ck => $cv) {
                            $sku_spec_format[] = $cv[ 'spec_value_name' ];
                        }
                        $sku_data = [
                            'site_id' => $this->site_id,
                            'sku_name' => $v[ 'spec_name' ],
                            'sku_image' => !empty($v[ 'sku_image' ]) ? $v[ 'sku_image' ] : $data[ 'goods_cover' ],
                            'sku_no' => $v[ 'sku_no' ],
                            'goods_id' => $goods_id,
                            'sku_spec_format' => implode(',', $sku_spec_format), // sku规格格式
                            'market_price' => $v[ 'market_price' ],
                            'cost_price' => $v[ 'cost_price' ],
                            'weight' => $v[ 'weight' ],
                            'volume' => $v[ 'volume' ],
                            'stock' => $v[ 'stock' ],
                            'is_default' => $v[ 'is_default' ]
                        ];

                        // 未参与营销活动，则允许修改 原价、销售价
                        if ($active_goods_count == 0) {
                            $sku_data[ 'price' ] = $v[ 'price' ];
                            $sku_data[ 'sale_price' ] = $v[ 'price' ];
                        }

                        if (!empty($v[ 'sku_id' ])) {
                            // 修改规格
                            $sku_id_arr[] = $v[ 'sku_id' ];
                            $goods_sku_model->where([ [ 'sku_id', '=', $v[ 'sku_id' ] ], [ 'goods_id', '=', $goods_id ] ])->update($sku_data);
                        } else {
                            // 新增规格
                            $sku_model = $goods_sku_model->create($sku_data);
                            $sku_id_arr[] = $sku_model->sku_id;
                        }
                        if ($v[ 'is_default' ] == 1) {

                            $goods_price = $v[ 'price' ] ?? $goods_sku_model->price;
                            $default_spec_count++;
                        }
                    }

                    // 校验默认必须存在默认规格
                    if ($default_spec_count == 0) throw new AdminException('SHOP_GOODS_NOT_HAS_DEFAULT_SPEC');

                    $spec_id_list = $goods_spec_model->withSearch([ "goods_id" ], [ 'goods_id' => $goods_id ])->field('spec_id')->select()->toArray();
                    $spec_id_list = array_column($spec_id_list, 'spec_id');

                    // 商品规格值
                    foreach ($data[ 'goods_spec_format' ] as $k => $v) {
                        $spec_values = [];
                        foreach ($v[ 'values' ] as $ck => $cv) {
                            $spec_values[] = $cv[ 'spec_value_name' ];
                        }
                        $spec_data = [
                            'goods_id' => $goods_id,
                            'spec_name' => $v[ 'spec_name' ],
                            'spec_values' => implode(',', $spec_values)
                        ];
                        if (!empty($v[ 'spec_id' ])) {
                            // 修改规格值
                            $goods_spec_model->where([ [ 'goods_id', '=', $goods_id ], [ 'spec_id', '=', $v[ 'spec_id' ] ] ])->update($spec_data);
                            foreach ($spec_id_list as $ck => $cv) {
                                if ($v[ 'spec_id' ] == $cv) {
                                    unset($spec_id_list[ $ck ]);
                                }
                            }

                        } else {
                            // 添加规格值
                            $goods_spec_model->save($spec_data);
                        }
                    }

                    // 移除不存在的规格项
                    if (!empty($spec_id_list)) {
                        $goods_spec_model->where([ [ 'spec_id', 'in', implode(',', $spec_id_list) ] ])->delete();
                    }

                    // 移除不存在的商品SKU
                    $sku_id_list = $goods_sku_model->withSearch([ "goods_id" ], [ 'goods_id' => $goods_id ])->field('sku_id')->select()->toArray();
                    $sku_id_list = array_column($sku_id_list, 'sku_id');
                    foreach ($sku_id_list as $k => $v) {
                        foreach ($sku_id_arr as $ck => $cv) {
                            if ($v == $cv) {
                                unset($sku_id_list[ $k ]);
                            }
                        }
                    }
                    $sku_id_list = array_values($sku_id_list);

                    if (!empty($sku_id_list)) {

                        // 检测订单是否存在要删除的商品
                        $order_where = [
                            [ 'orderMain.status', 'in', [ OrderDict::NORMAL, OrderDict::WAIT_DELIVERY, OrderDict::WAIT_TAKE ] ], // 排除已完成、已关闭状态
                            [ 'goods_id', '=', $goods_id ],
                            [ 'sku_id', 'in', $sku_id_list ]
                        ];
                        $order_goods_count = $order_goods_model
                            ->withJoin([ 'orderMain' ])
                            ->where($order_where)->count();

                        if ($order_goods_count > 0) {
                            Db::rollback();
                            throw new CommonException('EXIST_ORDER_NOT_DELETE_GOODS');
                        }

                        $goods_sku_model->where([ [ 'sku_id', 'in', implode(',', $sku_id_list) ] ])->delete();
                    }

                } else {

                    // 检测订单是否存在要删除的商品
                    $order_where = [
                        [ 'orderMain.status', 'in', [ 1, 2, 3 ] ], // 排除已完成、已关闭状态
                        [ 'goods_id', '=', $goods_id ]
                    ];
                    $order_goods_count = $order_goods_model
                        ->withJoin([ 'orderMain' ])
                        ->where($order_where)->count();

                    if ($order_goods_count > 0) {
                        Db::rollback();
                        throw new CommonException('EXIST_ORDER_NOT_EDIT_GOODS');
                    }

                    // 规格项发生变化，删除旧规格，添加新规格重新生成
                    $goods_sku_model->where([ [ 'goods_id', '=', $goods_id ] ])->delete();
                    $goods_spec_model->where([ [ 'goods_id', '=', $goods_id ] ])->delete();

                    $default_spec_count = 0;
                    foreach ($data[ 'goods_sku_data' ] as $k => $v) {
                        $sku_spec_format = [];
                        foreach ($v[ 'sku_spec' ] as $ck => $cv) {
                            $sku_spec_format[] = $cv[ 'spec_value_name' ];
                        }
                        $sku_data[] = [
                            'site_id' => $this->site_id,
                            'sku_name' => $v[ 'spec_name' ],
                            'sku_image' => !empty($v[ 'sku_image' ]) ? $v[ 'sku_image' ] : $data[ 'goods_cover' ],
                            'sku_no' => $v[ 'sku_no' ],
                            'goods_id' => $goods_id,
                            'sku_spec_format' => implode(',', $sku_spec_format), // sku规格格式
                            'price' => $v[ 'price' ],
                            'sale_price' => $v[ 'price' ],
                            'market_price' => $v[ 'market_price' ],
                            'cost_price' => $v[ 'cost_price' ],
                            'stock' => $v[ 'stock' ],
                            'weight' => $v[ 'weight' ],
                            'volume' => $v[ 'volume' ],
                            'is_default' => $v[ 'is_default' ]
                        ];
                        if ($v[ 'is_default' ] == 1) $default_spec_count++;
                    }

                    // 校验默认必须存在默认规格
                    if ($default_spec_count == 0) throw new AdminException('SHOP_GOODS_NOT_HAS_DEFAULT_SPEC');

                    $goods_sku_model->saveAll($sku_data);

                    // 商品规格值
                    $spec_data = [];
                    foreach ($data[ 'goods_spec_format' ] as $k => $v) {
                        $spec_values = [];
                        foreach ($v[ 'values' ] as $ck => $cv) {
                            $spec_values[] = $cv[ 'spec_value_name' ];
                        }
                        $spec_data[] = [
                            'goods_id' => $goods_id,
                            'spec_name' => $v[ 'spec_name' ],
                            'spec_values' => implode(',', $spec_values)
                        ];
                    }
                    $goods_spec_model->saveAll($spec_data);

                }

            }

            (new CoreGoodsPriceWriteService())->finish((int)$this->site_id, $goods_id,
                $data['spec_type'] === 'single' ? [$data] : (array)$data['goods_sku_data'], $priceBefore, false, $active_goods_count > 0);
            // 推广素材必须使用最终普通售价，不能把员工输入的会员基准价当作零售价。
            $goods_price = GoodsSku::where('site_id', $this->site_id)->where('goods_id', $goods_id)->where('is_default', 1)->value('price');
            (new CoreGoodsChangeLogService())->record((int)$this->site_id, $goods_id, $auditBefore, 'edit');
            Db::commit();

            event('AfterGoodsEdit', [
                'goods_id' => $goods_id,
                'goods_data' => $goods_data,
                'sku_data' => $sku_data
            ]);
            event('PhoneShopGoodsSaleableChanged', ['site_id' => $this->site_id, 'goods_id' => $goods_id]);
            event('TreasureDataSync',[
                'site_id'=>$this->site_id,
                'relate_id'=>$goods_id,
                'relate_type'=>'phone_shop',
                'treasure_name'=>$data['goods_name'],
                'treasure_sub_name'=>$data['sub_title'],
                'treasure_image'=>$data['goods_image'],
                'treasure_price'=>$goods_price,
            ]);
            // 主站商品编辑变化 -> 重新铺货刷新子站副本(名称/价格/分类/状态等跟随)。仅主站生效,失败不影响编辑。
            try {
                $master_site_id = ( new \addon\phone_shop\app\service\core\agent\AgentConfigService() )->getMasterSiteId();
                if ($this->site_id == $master_site_id) {
                    ( new \addon\phone_shop\app\service\core\agent\GoodsDistributionService() )->distribute((int) $goods_id);
                }
            } catch (\Throwable $e) {
                \think\facade\Log::write('[phone_shop] 编辑后重铺货失败: ' . $e->getMessage());
            }
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 删除商品
     * @param $goods_ids
     * @return bool
     */
    public function del($data)
    {
        $is_all = $data[ 'is_all' ];
        $goods_ids = $data[ 'goods_ids' ];
        $where = $data[ 'where' ];
        // 查询商品参与营销活动的数量
        $active_goods_count = $this->getActiveGoodsCount($goods_ids, $is_all);
        if ($active_goods_count > 0) {
            throw new AdminException('SHOP_GOODS_PARTICIPATE_IN_ACTIVE_DISABLED_EDIT');
        }
        // 查询商品是否参与礼品卡活动
        $is_connected = event('GoodsIsConnectedCard', [ 'is_all' => $is_all, 'where' => $where, 'goods_ids' => $goods_ids, 'site_id' => $this->site_id ]);
        if (!empty($is_connected) && isset($is_connected[ 0 ]) && $is_connected[ 0 ]) {
            throw new AdminException('GOODS_PARTICIPATE_IN_ACTIVE_DISABLED_DELETE');
        }

        // 删除前先固定实际受影响的商品ID。批量筛选条件可能包含 status，更新后再查会漏掉联动对象。
        if (!$is_all) {
            $base_where = [
                [ 'site_id', '=', $this->site_id ],
                [ 'goods_id', 'in', $goods_ids ]
            ];
            $affected = $this->model->where($base_where)->column('goods_id');
        } else {
            $affected = $this->getBatchAllQuery($data[ 'where' ], $goods_ids)->column('goods.goods_id');
        }
        $res = Db::transaction(function () use ($affected) {
            $updated = 0;
            foreach (array_chunk($affected, 500) as $chunk) {
                $updated += (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, $chunk, 'delete', function () use ($chunk) {
                    return $this->model->where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update(['status' => 0, 'delete_time' => time()]);
                });
            }
            return $updated;
        });
        $this->dispatchMasterGoodsStatus($affected, 0);
        event('PhoneShopGoodsSaleableChanged', [
            'site_id' => $this->site_id,
            'goods_ids' => $affected,
        ]);
        return $res;
    }

    /**
     * 恢复商品
     * @param $goods_ids
     * @return bool
     */
    public function recycle($goods_ids)
    {
        $affected = Db::name('phone_shop_goods')->where('site_id', $this->site_id)->whereIn('goods_id', $goods_ids)->where('delete_time', '>', 0)->column('goods_id');
        $res = Db::transaction(function () use ($affected) {
            foreach (array_chunk($affected, 500) as $chunk) {
                (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, $chunk, 'restore', function () use ($chunk) {
                    return $this->model->restore([['goods_id', 'in', $chunk], ['site_id', '=', $this->site_id]]);
                });
            }
            return true;
        });
        return $res;
    }

    /**
     * 获取商品类型
     * @return array|mixed|string
     */
    public function getType()
    {
        return GoodsDict::getType();
    }

    /**
     * 修改商品排序号
     * @param array $data
     * @return Goods|bool
     */
    public function editSort($data)
    {
        return (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, [(int)$data['goods_id']], 'sort', function () use ($data) {
            return $this->model->where([['goods_id', '=', $data['goods_id']], ['site_id', '=', $this->site_id]])->update(['sort' => $data['sort']]);
        });
    }

    /**
     * 修改商品上下架状态
     * @param $data
     * @return Goods
     */
    public function editStatus($data)
    {
        $is_all = $data[ 'is_all' ];
        $explode_goods_ids = [];
        if ($data[ 'status' ] == 0) {
            // 查询商品参与营销活动的数量
            $explode_goods_ids = $this->getActiveGoodsIds($data[ 'goods_ids' ], $is_all, $data[ 'where' ]);

        }
        if (!$is_all) {
            $base_where = [
                [ 'site_id', '=', $this->site_id ],
                [ 'goods_id', 'in', $data[ 'goods_ids' ] ]
            ];
            $affected = $this->model->where($base_where)
                ->whereNotIn('goods_id', $explode_goods_ids)->column('goods_id');
        } else {
            $explode_goods_ids = array_merge($explode_goods_ids, $data[ 'goods_ids' ]);
            $affected = $this->getBatchAllQuery($data[ 'where' ], $explode_goods_ids)->column('goods.goods_id');
        }
        if ((int)$data['status'] === 1) $this->assertRelistable($affected);
        $res = Db::transaction(function () use ($affected, $data) {
            $updated = 0;
            foreach (array_chunk($affected, 500) as $chunk) {
                $updated += (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, $chunk, 'status', function () use ($chunk, $data) {
                    return $this->model->where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update(['status' => $data['status']]);
                });
            }
            return $updated;
        });
        // 主站上下架 -> 联动所有启用站点关系的从站副本。
        $this->dispatchMasterGoodsStatus($affected, (int) $data[ 'status' ]);
        event('PhoneShopGoodsSaleableChanged', [
            'site_id' => $this->site_id,
            'goods_ids' => $affected,
        ]);
        return $res;
    }

    /**
     * 修改商品上下架状态（单商品）
     * @param $data
     * @return bool
     */
    public function editSingleStatus($data)
    {
        if ((int)$data['status'] === 1) $this->assertRelistable([(int)$data['goods_id']]);
        $explode_goods_ids = [];
        if ($data[ 'status' ] == 0) {
            // 查询商品参与营销活动的数量
            $explode_goods_ids = $this->getActiveGoodsIds($data[ 'goods_id' ]);
        }
        if (!empty($explode_goods_ids)) throw new AdminException('SHOP_GOODS_PARTICIPATE_IN_ACTIVE_DISABLED_EDIT');

        (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, [(int)$data['goods_id']], 'status', function () use ($data) {
            return $this->model->where('site_id', $this->site_id)->where('goods_id', $data['goods_id'])->update(['status' => $data['status']]);
        });
        // 主站上下架 -> 铺货/联动子站
        $this->dispatchMasterGoodsStatus([ $data[ 'goods_id' ] ], (int) $data[ 'status' ]);
        event('PhoneShopGoodsSaleableChanged', [
            'site_id' => $this->site_id,
            'goods_id' => $data[ 'goods_id' ],
        ]);
        return true;
    }

    private function assertRelistable(array $goodsIds): void
    {
        $blocked = $this->model->where('site_id', $this->site_id)->whereIn('goods_id', $goodsIds)
            ->where(function ($q) { $q->whereIn('sale_status', ['sold', 'locked'])->whereOr('stock', '<=', 0); })
            ->limit(3)->column('goods_name');
        if ($blocked) throw new AdminException('以下商品仍被订单占用、已售出或没有库存，未执行上架：' . implode('、', $blocked) . '。未付款请关闭原单；已挂账/付款请先办理原单退货并确认实物收回');
        $assetIds = GoodsSku::where('site_id', $this->site_id)->whereIn('goods_id', $goodsIds)->where('erp_asset_id', '>', 0)->column('erp_asset_id');
        $inventory = new \addon\phone_shop\app\service\core\order\CoreOrderInventoryService();
        foreach (array_unique(array_map('intval', $assetIds)) as $assetId) $inventory->guardErpSale((int)$this->site_id, $assetId);
    }

    /**
     * 主站商品上下架后：上架时补建/刷新全部订阅站点副本，下架时同步关闭；
     * 仅当前站为主站才触发；子站自身上下架不触发。单向：主站 -> 子站。
     * @param array|string $goods_ids
     * @param int $status 1上架 0下架
     * @return void
     */
    private function dispatchMasterGoodsStatus($goods_ids, int $status): void
    {
        try {
            $master_site_id = ( new \addon\phone_shop\app\service\core\agent\AgentConfigService() )->getMasterSiteId();
            if ($this->site_id != $master_site_id) return;

            if (is_string($goods_ids)) $goods_ids = explode(',', $goods_ids);
            $goods_ids = array_values(array_filter(array_map('intval', (array) $goods_ids)));
            if (empty($goods_ids)) return;

            $sync = new \addon\phone_shop\app\service\core\agent\GoodsSyncService();
            foreach ($goods_ids as $goods_id) {
                // 以主站商品ID精确同步，goods_no 只作为副本公共编号。
                $sync->syncGoods((int)$goods_id, $master_site_id, $status);
            }
        } catch (\Throwable $e) {
            \think\facade\Log::write('[phone_shop] dispatchMasterGoodsStatus: ' . $e->getMessage());
        }
    }

    /**
     * 复制商品
     * @param int $goods_id
     * @return mixed
     */
    public function copy(int $goods_id)
    {
        try {
            Db::startTrans();
            $goods_sku_model = new GoodsSku();
            $goods_spec_model = new GoodsSpec();

            // 查询商品信息
            $field = 'goods_name,site_id,sub_title,goods_type,goods_cover,goods_image,goods_video,goods_desc,brand_id,goods_category,label_ids,service_ids,unit,stock,virtual_sale_num,is_limit,limit_type,max_buy,min_buy,status,sort,delivery_type,is_free_shipping,fee_type,delivery_money,delivery_template_id,supplier_id,attr_ids,attr_format,virtual_auto_delivery,virtual_receive_type,virtual_verify_type,virtual_indate,poster_id,form_id';

            $goods_data = $this->model->field($field)->where([ [ 'goods_id', '=', $goods_id ], [ 'site_id', '=', $this->site_id ] ])->findOrEmpty()->toArray();
            if (empty($goods_data)) {
                throw new AdminException('SHOP_GOODS_NOT_EXIST');
            }

            // 初始化数据
            $goods_data[ 'goods_name' ] .= '_副本';
            $goods_data[ 'sale_num' ] = 0;
            $goods_data[ 'create_time' ] = time();
            $goods_data[ 'sort' ] = 0;
            $goods_data[ 'status' ] = 0;

            // 添加商品
            $res = $this->model->create($goods_data);

            // 查询商品规格信息
            $sku_field = 'sku_id,site_id,sku_name,sku_image,sku_no,goods_id,sku_spec_format,price,market_price,cost_price,stock,weight,volume,is_default';

            $sku_order = 'sku_id asc';
            $goods_sku_list = $goods_sku_model->withSearch([ "goods_id" ], [ 'goods_id' => $goods_id ])->field($sku_field)->order($sku_order)->select()->toArray();

            // 添加商品规格
            foreach ($goods_sku_list as $k => $v) {
                unset($goods_sku_list[ $k ][ 'sku_id' ]);
                $goods_sku_list[ $k ][ 'sale_num' ] = 0;
                $goods_sku_list[ $k ][ 'goods_id' ] = $res->goods_id;
            }
            $goods_sku_model->saveAll($goods_sku_list);

            // 查询规格值信息
            $spec_field = 'spec_id,goods_id,spec_name,spec_values';
            $spec_order = 'spec_id asc';
            $spec_list = $goods_spec_model->withSearch([ "goods_id" ], [ 'goods_id' => $goods_id ])->field($spec_field)->order($spec_order)->select()->toArray();

            // 添加规格项/值
            if (!empty($spec_list)) {
                foreach ($spec_list as $k => $v) {
                    unset($spec_list[ $k ][ 'spec_id' ]);
                    $spec_list[ $k ][ 'goods_id' ] = $res->goods_id;
                }
                $goods_spec_model->saveAll($spec_list);
            }

            (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$res->goods_id, [], 'create');
            Db::commit();
            return $res->goods_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 查询回收站商品分页列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DbException
     */
    public function getRecyclePage(array $where = [])
    {
        $field = 'goods_id,site_id,goods_name,goods_type,goods_cover,goods_category,unit,stock,sale_num,virtual_sale_num,status,create_time,update_time';
        $order = 'create_time desc';
        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goodsSku.site_id', '=', $this->site_id ]
        ];
        if (!empty($where[ 'order' ])) {
            $order = $where[ 'order' ] . ' ' . $where[ 'sort' ];
        }

        $search_model = $this->model->onlyTrashed()->withSearch([ "goods_id", "goods_name", "goods_type", "goods_category" ], $where)
            ->field($field)->withJoin([
                'goodsSku' => [ 'sku_id', 'sku_name', 'goods_id', 'price', 'stock' ],
            ])->where($sku_where)->order($order)->append([ 'goods_type_name', 'goods_edit_path', 'goods_cover_thumb_small' ]);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商品选择分页列表
     * @param array $where
     * @return array
     */
    public function getSelectPage(array $where = [])
    {
        $field = 'site_id, goods_id, goods_name, goods_type, goods_cover,goods_image, stock,sub_title,goods_desc,is_gift';
        $order = 'sort desc,create_time desc';

        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goods.site_id', '=', $this->site_id ],
        ];
        if (!empty($where[ 'start_price' ]) && !empty($where[ 'end_price' ])) {
            $money = [ $where[ 'start_price' ], $where[ 'end_price' ] ];
            sort($money);
            $sku_where[] = [ 'goodsSku.price', 'between', $money ];
        } else if (!empty($where[ 'start_price' ])) {
            $sku_where[] = [ 'goodsSku.price', '>=', $where[ 'start_price' ] ];
        } else if (!empty($where[ 'end_price' ])) {
            $sku_where[] = [ 'goodsSku.price', '<=', $where[ 'end_price' ] ];
        }

        if (isset($where[ 'is_gift' ]) && $where[ 'is_gift' ] == GoodsDict::IS_GIFT) {
            $sku_where[] = [ 'goods.is_gift', 'in', [ GoodsDict::NOT_IS_GIFT, GoodsDict::IS_GIFT ] ];
        } else {
            $sku_where[] = [ 'goods.is_gift', '=', GoodsDict::NOT_IS_GIFT ];
        }

        if (!empty($where[ 'keyword' ])) {
            $sku_where[] = [ 'goods_name|sub_title', 'like', '%' . $where[ 'keyword' ] . '%' ];
        }

        // 查询已选的
        if (!empty($where[ 'sku_ids' ])) {
            $goods_sku_model = new GoodsSku();
            $goods_ids = $goods_sku_model->where([
                [ 'sku_id', 'in', $where[ 'sku_ids' ] ]
            ])->field('goods_id')->select()->toArray();
            if (!empty($goods_ids)) {
                $goods_ids = array_column($goods_ids, 'goods_id');
            }
        }

        if (!empty($goods_ids) && empty($where[ 'goods_ids' ])) {
            $where[ 'goods_ids' ] = $goods_ids;
        }
        if (!empty($where[ 'goods_type' ])) {
            $sku_where[] = [ 'goods.goods_type', '=', $where[ 'goods_type' ] ];
        }

        if ($where[ 'select_type' ] == 'all') {
            $sku_where[] = [ 'goods.stock', '>', 0 ];
            $sku_where[] = [ 'status', '=', 1 ];
        }
        if ($where[ 'select_type' ] == 'selected') {
            $sku_where[] = [ 'goods.goods_id', 'in', $where[ 'goods_ids' ] ];
        }

        $verify_goods_ids = [];
        $verify_sku_ids = [];
        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where[ 'verify_goods_ids' ])) {
            $verify_goods_ids = $this->model->where([
                [ 'goods_id', 'in', $where[ 'verify_goods_ids' ] ],
                [ 'status', '=', 1 ]
            ])->field('goods_id')->select()->toArray();

            if (!empty($verify_goods_ids)) {
                $verify_goods_ids = array_column($verify_goods_ids, 'goods_id');
            }
        }

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where[ 'verify_sku_ids' ])) {
            $goods_sku_model = new GoodsSku();
            $verify_sku_ids = $goods_sku_model->where([
                [ 'sku_id', 'in', $where[ 'verify_sku_ids' ] ]
            ])->field('sku_id')->select()->toArray();

            if (!empty($verify_sku_ids)) {
                $verify_sku_ids = array_column($verify_sku_ids, 'sku_id');
            }
        }

        $search_model = $this->model
            ->withSearch([ "goods_category", "goods_type" ], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => [ 'sku_id', 'sku_name', 'goods_id', 'price', 'stock', 'sku_spec_format' ],
            ])
            ->with([
                'skuList'
            ])
            ->where($sku_where)->order($order)->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ]);
        $list = $this->pageQuery($search_model);

        $list[ 'verify_goods_ids' ] = $verify_goods_ids;
        $list[ 'verify_sku_ids' ] = $verify_sku_ids;

        return $list;
    }

    /**
     * 获取商品选择分页列表
     * @param array $where
     * @return array
     */
    public function getSelectSku(array $where = [])
    {
        $field = 'site_id, goods_id, goods_name, goods_type, goods_cover, stock,is_gift';
        $order = 'sort desc,create_time desc';

        $select_goods_list = [];// 已选商品列表

        if (isset($where[ 'is_gift' ]) && $where[ 'is_gift' ] == GoodsDict::IS_GIFT) {
            $sku_where[] = [ 'goods.is_gift', 'in', [ GoodsDict::NOT_IS_GIFT, GoodsDict::IS_GIFT ] ];
        } else {
            $sku_where[] = [ 'goods.is_gift', '=', GoodsDict::NOT_IS_GIFT ];
        }

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where[ 'verify_goods_ids' ])) {
            $verify_goods_ids = $this->model->where([
                [ 'goods_id', 'in', $where[ 'verify_goods_ids' ] ],
                [ 'status', '=', 1 ]
            ])->field('goods_id')->select()->toArray();

            if (!empty($verify_goods_ids)) {
                $verify_goods_ids = array_column($verify_goods_ids, 'goods_id');
            }

            $select_goods_list = $this->model
                ->field($field)
                ->withJoin([
                    'goodsSku' => [ 'sku_id', 'sku_name', 'goods_id', 'price', 'stock', 'sku_spec_format' ],
                ])
                ->with([
                    'skuList'
                ])
                ->where([
                    [ 'goodsSku.is_default', '=', 1 ],
                    [ 'goods.goods_id', 'in', $verify_goods_ids ]
                ])
                ->where($sku_where)
                ->order($order)->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ])
                ->select()->toArray();
        }

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where[ 'verify_sku_ids' ])) {
            $goods_sku_model = new GoodsSku();
            $verify_sku_ids = $goods_sku_model->where([
                [ 'sku_id', 'in', $where[ 'verify_sku_ids' ] ]
            ])->field('sku_id')->select()->toArray();

            if (!empty($verify_sku_ids)) {
                $verify_sku_ids = array_column($verify_sku_ids, 'sku_id');
            }

            $goods_ids = $goods_sku_model->where([
                [ 'sku_id', 'in', $verify_sku_ids ]
            ])->field('goods_id')->select()->toArray();
            if (!empty($goods_ids)) {
                $goods_ids = array_column($goods_ids, 'goods_id');
                $goods_ids = array_unique($goods_ids);
            }

            $select_goods_list = $this->model
                ->field($field)
                ->withJoin([
                    'goodsSku' => [ 'sku_id', 'sku_name', 'goods_id', 'price', 'stock', 'sku_spec_format' ],
                ])
                ->with([
                    'skuList'
                ])
                ->where([
                    [ 'goodsSku.is_default', '=', 1 ],
                    [ 'goods.goods_id', 'in', $goods_ids ]
                ])
                ->where($sku_where)
                ->order($order)->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ])
                ->select()->toArray();
        }

        return $select_goods_list;
    }

    /**
     * 获取商品选择分页列表（代客下单专用）
     * @param array $where
     * @return array
     */
    public function getBuyGoodsSelect(array $where = [])
    {
        $field = 'site_id, goods_id, goods_name, goods_type, goods_cover,goods_image, stock,sub_title,goods_desc,is_limit,limit_type,max_buy,min_buy,member_discount';
        $order = 'sort desc,create_time desc';

        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goods.site_id', '=', $this->site_id ],
            [ 'goods.stock', '>', 0 ],
            [ 'status', '=', 1 ],
            [ 'goods.is_gift', '=', GoodsDict::NOT_IS_GIFT ]
        ];

        if (!empty($where[ 'keyword' ])) {
            $sku_where[] = [ 'goods_name|sub_title', 'like', '%' . $where[ 'keyword' ] . '%' ];
        }

        $search_model = $this->model
            ->withSearch([ "goods_category", "goods_type" ], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => [ 'sku_id', 'sku_name', 'goods_id', 'price', 'stock', 'sku_spec_format', 'market_price', 'sale_price', 'member_price' ],
            ])
            ->where($sku_where)->order($order)->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ]);
        $list = $this->pageQuery($search_model);
        if (!empty($where[ 'member_id' ])) {
            $member_info = $this->getMemberInfo($where[ 'member_id' ]);
            foreach ($list[ 'data' ] as $k => &$v) {
                if (!empty($v[ 'goodsSku' ])) {
                    $v[ 'goodsSku' ][ 'member_price' ] = $this->getMemberPrice($member_info, $v[ 'member_discount' ], $v[ 'goodsSku' ][ 'member_price' ], $v[ 'goodsSku' ][ 'price' ]);
                }
                // 限购查询当前会员已购数量
                $has_buy = ( new CoreGoodsLimitBuyService() )->getGoodsHasBuyNumber($this->site_id, $where[ 'member_id' ], $v[ 'goods_id' ]);
                $v[ 'has_buy' ] = $has_buy;
                // 满减活动
                $manjian_info = ( new ManjianService() )->getManjianInfo([ 'goods_id' => $v[ 'goods_id' ], 'sku_id' => $v[ 'goodsSku' ][ 'sku_id' ], 'member_id' => $where[ 'member_id' ] ]);
                $v[ 'manjian_info' ] = $manjian_info;
            }
        }
        return $list;
    }

    /**
     * 获取已选商品分页列表（代客下单专用）
     * @param array $where
     * @return array
     */
    public function getBuyGoodsSelected(array $where = [])
    {
        $field = 'sku_id, goods_id, site_id, sku_name, sku_image, price, stock, member_price, sale_price';
        $goods_sku_model = new GoodsSku();
        $select_goods_list = $goods_sku_model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'sku_id', 'in', $where[ 'sku_ids' ] ]
        ])->with([ 'goods' ])->field($field)->append([ 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ])->select()->toArray();
        if (!empty($where[ 'member_id' ])) {
            $member_info = $this->getMemberInfo($where[ 'member_id' ]);
            foreach ($select_goods_list as $k => &$v) {
                if (!empty($v[ 'goods' ])) {
                    $v[ 'member_price' ] = $this->getMemberPrice($member_info, $v[ 'goods' ][ 'member_discount' ], $v[ 'member_price' ], $v[ 'price' ]);
                }
                // 限购查询当前会员已购数量
                $has_buy = ( new CoreGoodsLimitBuyService() )->getGoodsHasBuyNumber($this->site_id, $where[ 'member_id' ], $v[ 'goods_id' ]);
                $v[ 'has_buy' ] = $has_buy;
                // 满减活动
                $manjian_info = ( new ManjianService() )->getManjianInfo([ 'goods_id' => $v[ 'goods_id' ], 'sku_id' => $v[ 'sku_id' ], 'member_id' => $where[ 'member_id' ] ]);
                $v[ 'manjian_info' ] = $manjian_info;
            }
        }
        return $select_goods_list;
    }

    /**
     * 获取商品规格信息，切换规格（代客下单专用）
     * @param array $data
     * @return array
     */
    public function getBuySkuSelect(array $data)
    {

        $field = 'site_id,sku_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, sale_num, is_default,member_price';

        $goods_sku_model = new GoodsSku();

        $info = $goods_sku_model->where([ [ 'site_id', '=', $this->site_id ], [ 'sku_id', '=', $data[ 'sku_id' ] ] ])
            ->field($field)
            ->with([
                // 商品主表
                'goods' => function ($query) {
                    $query->withField('goods_id, goods_name, goods_type, sub_title, goods_cover, unit, stock, sale_num + virtual_sale_num as sale_num, status,member_discount,is_discount')
                        ->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid', 'goods_cover_thumb_big' ]);
                },
                // 商品规格列表
                'skuList' => function ($query) {
                    $query->field('site_id, sku_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, is_default,member_price');
                },
                // 商品规格项/规格值列表
                'goodsSpec' => function ($query) {
                    $query->field('spec_id, goods_id, spec_name, spec_values');
                },
            ])
            ->append([ 'sku_image_thumb_small', 'sku_image_thumb_mid', 'sku_image_thumb_big' ])
            ->findOrEmpty()->toArray();
        if (!empty($info) && !empty($data[ 'member_id' ])) {
            $member_info = $this->getMemberInfo($data[ 'member_id' ]);

            $info[ 'member_price' ] = $this->getMemberPrice($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'member_price' ], $info[ 'price' ]);

            $this->getMemberPriceByList($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'skuList' ]);
            // 限购查询当前会员已购数量
            $has_buy = ( new CoreGoodsLimitBuyService() )->getGoodsHasBuyNumber($this->site_id, $data[ 'member_id' ], $info[ 'goods_id' ]);
            $info[ 'has_buy' ] = $has_buy;
            // 满减活动
            $manjian_info = ( new ManjianService() )->getManjianInfo([ 'goods_id' => $info[ 'goods_id' ], 'sku_id' => $info[ 'sku_id' ], 'member_id' => $data[ 'member_id' ] ]);
            $info[ 'manjian_info' ] = $manjian_info;
        }

        return $info;
    }

    /**
     * 查询商品SKU规格列表
     * @param $params
     * @return array
     */
    public function getSkuList($params)
    {
        $goods_sku_model = new GoodsSku();

        $field = 'sku_id, sku_name, sku_image,sku_no, goods_id, sku_spec_format, price, market_price, sale_price, cost_price, stock, weight, volume,member_price,device_snapshot';
        $order = 'sku_id asc';
        // SKU 编辑弹窗只依赖少量商品摘要字段。这里不能直接预载完整 Goods
        // 关联：历史站点的 goods JSON 字段可能仍保存为 0 等整数值，PHP 8
        // 下 ORM 在关联模型水合阶段会直接 json_decode(int) 并导致整个接口失败。
        // SKU 与商品摘要分开读取，既避免历史脏数据阻断 SKU 查询，也不扩大接口返回。
        $list = $goods_sku_model
            ->where([ [ 'site_id', '=', $this->site_id ] ])
            ->withSearch([ "goods_id" ], [ 'goods_id' => $params[ 'goods_id' ] ])
            ->field($field)
            ->order($order)
            ->select()
            ->toArray();

        if (!empty($list)) {
            $goodsIds = array_values(array_unique(array_map(
                static fn(array $sku): int => (int)$sku[ 'goods_id' ],
                $list
            )));
            $goodsList = (new Goods())
                ->where([ [ 'site_id', '=', $this->site_id ] ])
                ->whereIn('goods_id', $goodsIds)
                ->field('goods_id,site_id,goods_name,goods_type,goods_cover')
                ->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ])
                ->select()
                ->toArray();
            $goodsMap = array_column($goodsList, null, 'goods_id');

            foreach ($list as &$sku) {
                $sku[ 'goods' ] = $goodsMap[ $sku[ 'goods_id' ] ] ?? [];
            }
            unset($sku);
        }
        $pricingPolicy = (new CoreTierPricingService())->policy((int)$this->site_id);
        foreach ($list as &$sku) {
            $sku['pricing_base_price'] = CoreTierPricingService::baseFromSku($sku, $pricingPolicy);
            unset($sku['device_snapshot']);
        }
        unset($sku);
        return $list;
    }

    /**
     * 商品数统计
     * @return int[]
     * @throws \think\db\exception\DbException
     */
    public function getGoodsCount()
    {
        $data = [
            "sale_goods_num" => 0, //销售
            "warehouse_goods_num" => 0, //仓库
        ];

        $data[ 'sale_goods_num' ] = $this->model->where([ [ 'status', '=', 1 ], [ 'site_id', '=', $this->site_id ] ])->count();
        $data[ 'warehouse_goods_num' ] = $this->model->where([ [ 'status', '=', 0 ], [ 'site_id', '=', $this->site_id ] ])->count();
        return $data;
    }

    /**
     * 编辑商品规格列表库存
     * @param $params
     * @return array|bool
     */
    public function editGoodsListStock($params)
    {
        try {
            Db::startTrans();
            $auditBefore = (new CoreGoodsChangeLogService())->capture((int)$this->site_id, (int)$params['goods_id']);

            $goods_info = $this->model->where([
                [ 'goods_id', '=', $params[ 'goods_id' ] ],
                [ 'site_id', '=', $this->site_id ]
            ])->field('goods_type')->findOrEmpty()->toArray();

            if (empty($goods_info)) {
                throw new CommonException('SHOP_GOODS_NOT_EXIST');
            }

            $sku_list = $params[ 'sku_list' ];
            if (!empty($sku_list)) {
                $goods_stock = 0; // 总库存
                $goods_sku_model = new GoodsSku();
                foreach ($sku_list as $k => $v) {
                    $goods_stock += (int) $v[ 'stock' ];

                    $update_data = [
                        'stock' => $v[ 'stock' ],
                    ];

                    $goods_sku_model->where([
                        [ 'goods_id', '=', $params[ 'goods_id' ] ],
                        [ 'sku_id', '=', $v[ 'sku_id' ] ]
                    ])->update($update_data);
                }
                $this->model->where([
                    [ 'goods_id', '=', $params[ 'goods_id' ] ]
                ])->update([
                    'stock' => $goods_stock,
                ]);
            }
            (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$params['goods_id'], $auditBefore, 'stock');
            Db::commit();
            event('PhoneShopGoodsSaleableChanged', [
                'site_id' => $this->site_id,
                'goods_id' => $params[ 'goods_id' ],
            ]);
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 编辑商品规格列表价格
     * @param $params
     * @return array|bool
     */
    public function editGoodsListPrice($params)
    {
        try {
            Db::startTrans();
            $priceBefore = (new CoreGoodsPriceWriteService())->capture((int)$this->site_id, (int)$params['goods_id']);
            $auditBefore = (new CoreGoodsChangeLogService())->capture((int)$this->site_id, (int)$params['goods_id']);

            $goods_info = $this->model->where([
                [ 'goods_id', '=', $params[ 'goods_id' ] ],
                [ 'site_id', '=', $this->site_id ]
            ])->field('goods_id,goods_type')->findOrEmpty()->toArray();

            if (empty($goods_info)) {
                throw new CommonException('SHOP_GOODS_NOT_EXIST');
            }

            // 查询商品参与营销活动的数量
            $active_goods_count = $this->getActiveGoodsCount($goods_info[ 'goods_id' ]);

            $sku_list = $params[ 'sku_list' ];
            if (!empty($sku_list)) {
                $goods_sku_model = new GoodsSku();
                foreach ($sku_list as $k => $v) {
                    $update_data = [
                        'cost_price' => $v[ 'cost_price' ],
                        'market_price' => $v[ 'market_price' ],
                    ];
                    if (($params['member_discount'] ?? null) === 'fixed_price' && isset($v['member_price'])) {
                        if ($active_goods_count > 0) throw new CommonException('商品参加营销活动，暂不能修改会员价格');
                        $update_data['member_price'] = $this->encodeMemberPrice($v['member_price']);
                    }

                    if ($active_goods_count == 0) {
                        $update_data[ 'price' ] = $v[ 'price' ];
                        $update_data[ 'sale_price' ] = $v[ 'price' ];
                    }

                    $goods_sku_model->where([
                        [ 'goods_id', '=', $params[ 'goods_id' ] ],
                        [ 'sku_id', '=', $v[ 'sku_id' ] ]
                    ])->update($update_data);
                }
            }
            if (($params['member_discount'] ?? null) === 'fixed_price') {
                $this->model->where('site_id', $this->site_id)->where('goods_id', $params['goods_id'])->update(['member_discount' => 'fixed_price']);
            }
            (new CoreGoodsPriceWriteService())->finish((int)$this->site_id, (int)$params['goods_id'], (array)$params['sku_list'], $priceBefore, false, $active_goods_count > 0);
            (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$params['goods_id'], $auditBefore, 'price');
            Db::commit();
            event('PhoneShopGoodsSaleableChanged', [
                'site_id' => $this->site_id,
                'goods_id' => $params[ 'goods_id' ],
            ]);
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 编辑商品规格列表会员价格
     * @param $params
     * @return array|bool
     */
    public function editGoodsListMemberPrice($params)
    {
        if ((int)(new CoreTierPricingService())->policy((int)$this->site_id)['enabled'] === 1) {
            throw new CommonException('已开启自动加价，各等级价格由基准售价生成，请使用“修改价格”');
        }
        try {
            Db::startTrans();
            $priceBefore = (new CoreGoodsPriceWriteService())->capture((int)$this->site_id, (int)$params['goods_id']);
            $auditBefore = (new CoreGoodsChangeLogService())->capture((int)$this->site_id, (int)$params['goods_id']);
            if ($this->getActiveGoodsCount((int)$params['goods_id']) > 0) throw new CommonException('商品参与营销活动，暂不能修改会员价格');

            $goods_info = $this->model->where([
                [ 'goods_id', '=', $params[ 'goods_id' ] ],
                [ 'site_id', '=', $this->site_id ]
            ])->field('goods_type')->findOrEmpty()->toArray();

            if (empty($goods_info)) {
                throw new CommonException('SHOP_GOODS_NOT_EXIST');
            }

            // 修改商品的会员等级折扣
            $this->model->where([
                [ 'goods_id', '=', $params[ 'goods_id' ] ],
                [ 'site_id', '=', $this->site_id ]
            ])->update([
                'member_discount' => $params[ 'member_discount' ]
            ]);

            $sku_list = $params[ 'sku_list' ];
            if (!empty($sku_list)) {
                $goods_sku_model = new GoodsSku();
                foreach ($sku_list as $k => $v) {
                    $update_data = [
                        'member_price' => $this->encodeMemberPrice($v[ 'member_price' ] ?? ''),
                    ];

                    $goods_sku_model->where([
                        [ 'goods_id', '=', $params[ 'goods_id' ] ],
                        [ 'sku_id', '=', $v[ 'sku_id' ] ]
                    ])->update($update_data);
                }
            }
            (new CoreGoodsPriceWriteService())->finish((int)$this->site_id, (int)$params['goods_id'], (array)$params['sku_list'], $priceBefore);
            (new CoreGoodsChangeLogService())->record((int)$this->site_id, (int)$params['goods_id'], $auditBefore, 'member_price');
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage() . '，Line：' . $e->getLine() . '，File：' . $e->getFile());
        }
    }

    /**
     * 查询商品参与营销活动的数量
     * @param $goods_id
     * @return mixed
     */
    public function getActiveGoodsCount($goods_id, $is_all = 0, $where = [], ?int $siteId = null)
    {
        // 判断 $goods_id 类型
        if (!is_array($goods_id)) {
            $goods_id = [ $goods_id ];
        }

        $join_list = event('GetGoodsJoinInfo', [
            'goods_ids' => $goods_id,
            'site_id' => $siteId ?? $this->site_id,
            'is_get_count' => 1,
            'is_all' => $is_all,
            'where' => $where,
        ]);
        return array_sum($join_list);
    }

    /**
     * 查询商品参与营销活动的数量
     * @param $goods_id
     * @return mixed
     */
    public function getActiveGoodsIds($goods_id, $is_all = 0, $where = [])
    {
        // 判断 $goods_id 类型
        if (!is_array($goods_id)) {
            $goods_id = [ $goods_id ];
        }

        $join_list = event('GetGoodsJoinInfo', [
            'goods_ids' => $goods_id,
            'site_id' => $this->site_id,
            'is_get_count' => 0,
            'is_all' => $is_all,
            'where' => $where,
        ]);
        $goods_ids = [];
        foreach ($join_list as $item) {
            $goods_ids = array_merge($goods_ids, array_keys($item));
        }
        return $goods_ids;
    }

    public function getMemberInfo($member_id)
    {
        $member_model = new Member();
        $member_field = 'member_level';
        $member_info = $member_model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $member_id ]
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
     * 从 member_price JSON 里取该会员等级的指定价。
     * 键统一口径=站内序号 level_no(优先);兼容旧数据=全局 level_id。取不到返回 null。
     */
    private function pickLevelPrice($member_price_json, $member_info)
    {
        return CoreMemberPriceService::pickLevelPrice($member_price_json, (array)$member_info);
    }

    /**
     * 统一会员价入库格式。
     *
     * 历史数据和导入数据可能传入 0、null、JSON 字符串或数组；会员价字段只保存
     * “会员等级 => 价格”的 JSON 对象，其他标量均按未设置会员价处理。
     */
    private function encodeMemberPrice($memberPrice): string
    {
        $prices = $this->decodeMemberPrice($memberPrice);
        if (empty($prices)) {
            return '';
        }

        $json = json_encode($prices, JSON_UNESCAPED_UNICODE);
        return $json === false ? '' : $json;
    }

    /**
     * 安全解析会员价，避免 PHP 8 对 json_decode 非字符串参数抛出 TypeError。
     */
    private function decodeMemberPrice($memberPrice): array
    {
        if (is_array($memberPrice)) {
            return $memberPrice;
        }

        if (!is_string($memberPrice) || trim($memberPrice) === '') {
            return [];
        }

        $decoded = json_decode($memberPrice, true);
        return is_array($decoded) ? $decoded : [];
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

    /**
     * 批量设置商品
     * @param $data
     * @return mixed
     */
    public function batchSet($data)
    {
        $is_all = (int)($data['is_all'] ?? 0);
        $goods_ids = array_values(array_unique(array_filter(array_map('intval', (array)($data['goods_ids'] ?? [])))));
        if (empty($data[ 'set_type' ])) throw new AdminException('NOT_GET_SET_TYPE');
        if (!$is_all && empty($goods_ids)) throw new AdminException('NOT_GET_SHOP_INFO');
        $target_ids = $is_all
            ? array_values(array_unique(array_map('intval', $this->getBatchAllQuery((array)($data['where'] ?? []), $goods_ids)->distinct(true)->column('goods.goods_id'))))
            : $this->model->where([['site_id', '=', $this->site_id]])->whereIn('goods_id', $goods_ids)->column('goods_id');
        $target_ids = array_values(array_unique(array_map('intval', $target_ids)));
        if (!$target_ids) throw new CommonException('当前选择范围内没有可设置的商品，请刷新筛选条件后重试');

        $filed_data = [];
        $setValue = (array)($data['set_value'] ?? []);
        switch ($data[ 'set_type' ]) {
            case GoodsDict::LABEL :
                $filed_data[ 'label' ][ 'label_ids' ] = array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'set_value' ][ 'label_ids' ]);
                break;
            case GoodsDict::SERVICE :
                $filed_data[ 'service' ][ 'service_ids' ] = array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'set_value' ][ 'service_ids' ]);
                break;
            case GoodsDict::VIRTUAL_SALE_NUM :
                $filed_data[ 'virtual_sale_num' ][ 'virtual_sale_num' ] = $data[ 'set_value' ][ 'virtual_sale_num' ];
                break;
            case GoodsDict::CATEGORY :
                if (empty($setValue['goods_category'])) throw new CommonException('请选择商品分类');
                $categoryIds = array_values(array_unique(array_map('intval', (array)$setValue['goods_category'])));
                $validCategoryIds = (new Category())->where([['site_id', '=', $this->site_id]])->whereIn('category_id', $categoryIds)->column('category_id');
                if (count($validCategoryIds) !== count($categoryIds)) throw new CommonException('所选分类中包含已删除或不属于本站的数据，请刷新分类后重试');
                $filed_data[ 'category' ][ 'goods_category' ] = array_map(function ($item) {
                    return (string) $item;
                }, $categoryIds);
                break;
            case GoodsDict::BRAND :
                $brandId = (int)($setValue['brand_id'] ?? 0);
                if ($brandId <= 0 || !(new Brand())->where([['site_id', '=', $this->site_id], ['brand_id', '=', $brandId]])->count()) {
                    throw new CommonException('请选择本站已有品牌');
                }
                $filed_data[ 'brand' ][ 'brand_id' ] = $brandId;
                break;
            case GoodsDict::MEMORY_GROUP:
                $memory = trim((string)($setValue['memory_group'] ?? ''));
                if ($memory === '') throw new CommonException('请选择或填写内存规格');
                $filed_data['memory_group']['memory_group'] = $memory;
                break;
            case GoodsDict::CONDITION_GRADE:
                $grade = trim((string)($setValue['condition_grade'] ?? ''));
                if ($grade === '') throw new CommonException('请选择商品等级');
                $filed_data['condition_grade']['condition_grade'] = $grade;
                break;
            case GoodsDict::DEVICE_COLOR:
                $filed_data['device_color']['device_color'] = (new CoreDeviceAttributeService())->normalizeColor($setValue['device_color'] ?? '');
                break;
            case GoodsDict::BATTERY_HEALTH:
                $filed_data['battery_health']['battery_health'] = (new CoreDeviceAttributeService())->normalizeBattery($setValue['battery_health'] ?? '');
                break;
            case GoodsDict::WARRANTY_EXPIRE_TIME:
                $filed_data['warranty_expire_time']['warranty_expire_time'] = (new CoreDeviceAttributeService())->normalizeWarrantyExpire($setValue['warranty_expire_time'] ?? '');
                break;
            case GoodsDict::POSTER :
                $filed_data[ 'poster' ][ 'poster_id' ] = $data[ 'set_value' ][ 'poster_id' ];
                break;
            case GoodsDict::DIY_FORM :
                $filed_data[ 'diy_form' ][ 'form_id' ] = $data[ 'set_value' ][ 'form_id' ];
                break;
            case GoodsDict::GIFT :
                if (!isset($data[ 'set_value' ][ 'is_gift' ]) || !in_array($data[ 'set_value' ][ 'is_gift' ], [ GoodsDict::IS_GIFT, GoodsDict::NOT_IS_GIFT ])) break;
                $filed_data[ 'gift' ][ 'is_gift' ] = $data[ 'set_value' ][ 'is_gift' ];
                break;
            case GoodsDict::DELIVERY :
                if (!isset($data[ 'set_value' ][ 'delivery_type' ]) || empty($data[ 'set_value' ][ 'delivery_type' ])) break;
                $filed_data[ 'delivery' ][ 'delivery_type' ] = array_map(function ($item) {
                    return (string) $item;
                }, $data[ 'set_value' ][ 'delivery_type' ] ?? []);
                $filed_data[ 'delivery' ][ 'is_free_shipping' ] = $data[ 'set_value' ][ 'is_free_shipping' ] ?? 1;
                $filed_data[ 'delivery' ][ 'fee_type' ] = $data[ 'set_value' ][ 'fee_type' ] ?? 'template';
                $filed_data[ 'delivery' ][ 'delivery_money' ] = $data[ 'set_value' ][ 'delivery_money' ] ?? 0;
                $filed_data[ 'delivery' ][ 'delivery_template_id' ] = $data[ 'set_value' ][ 'delivery_template_id' ] ?? 0;
                break;
            case GoodsDict::MEMBER_DISCOUNT :
                if ((int)(new CoreTierPricingService())->policy((int)$this->site_id)['enabled'] === 1) throw new CommonException('自动加价已启用，不能批量覆盖会员价格规则');
                $filed_data[ 'member_discount' ][ 'member_discount' ] = $data[ 'set_value' ][ 'member_discount' ];
                break;
            case GoodsDict::DIY_DETAIL :
                $filed_data[ 'diy_detail' ][ 'diy_detail_id' ] = $data[ 'set_value' ][ 'diy_detail_id' ];
                break;
            case GoodsDict::STOCK :
                if (!isset($data[ 'set_value' ][ 'stock_type' ]) || empty($data[ 'set_value' ][ 'stock_type' ]) || !isset($data[ 'set_value' ][ 'stock' ]) || $data[ 'set_value' ][ 'stock' ] <= 0) break;
                $update_stock = (int) $data[ 'set_value' ][ 'stock' ];

                Db::transaction(function () use ($target_ids, $data, $update_stock) {
                    foreach (array_chunk($target_ids, 500) as $chunk) {
                        (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, $chunk, 'batch', function () use ($chunk, $data, $update_stock) {
                            $stock = $data['set_value']['stock_type'] === 'inc' ? "stock + {$update_stock}" : "CASE WHEN stock >= $update_stock THEN stock - $update_stock ELSE 0 END";
                            (new GoodsSku())->where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update(['stock' => Db::raw($stock)]);
                            Goods::where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update([
                                'stock' => Db::raw("(SELECT COALESCE(SUM(stock), 0) FROM " . (new GoodsSku())->getTable() . " WHERE goods_id = " . (new Goods())->getTable() . ".goods_id AND site_id = " . (int)$this->site_id . ")")
                            ]);
                        });
                    }
                });
                return ['matched_count' => count($target_ids), 'updated_count' => count($target_ids)];
        }

        if (!$filed_data) throw new CommonException('本次批量设置没有有效内容');
        $field = array_key_first($filed_data);
        $updateData = [];
        foreach ($filed_data[$field] as $column => $value) $updateData[$column] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        if ($data['set_type'] === GoodsDict::DELIVERY) {
            $target_ids = $this->model->where([['site_id', '=', $this->site_id], ['goods_type', '=', GoodsDict::REAL]])->whereIn('goods_id', $target_ids)->column('goods_id');
        }
        if (!$target_ids) throw new CommonException('当前选择范围内没有符合设置条件的商品');
        $updated = 0;
        Db::transaction(function () use ($target_ids, $updateData, $data, &$updated) {
            foreach (array_chunk($target_ids, 500) as $chunk) {
                $updated += (int)(new CoreGoodsChangeLogService())->mutate((int)$this->site_id, $chunk, 'batch', function () use ($chunk, $updateData, $data) {
                    $count = (int)$this->model->where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update($updateData);
                    if ($data['set_type'] === GoodsDict::MEMBER_DISCOUNT) {
                        (new GoodsSku())->where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update(['member_price' => '']);
                    }
                    if ($data['set_type'] === GoodsDict::CONDITION_GRADE) {
                        (new GoodsSku())->where('site_id', $this->site_id)->whereIn('goods_id', $chunk)->update(['condition_grade' => $updateData['condition_grade']]);
                    }
                    return $count;
                });
            }
        });
        return ['matched_count' => count($target_ids), 'updated_count' => $updated];
    }

    public function getBatchAllQuery($where, $example_goods_ids = [])
    {
        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
        ];

        if (!empty($where[ 'start_price' ]) && !empty($where[ 'end_price' ])) {
            $money = [ $where[ 'start_price' ], $where[ 'end_price' ] ];
            sort($money);
            $sku_where[] = [ 'goodsSku.price', 'between', $money ];
        } else if (!empty($where[ 'start_price' ])) {
            $sku_where[] = [ 'goodsSku.price', '>=', $where[ 'start_price' ] ];
        } else if (!empty($where[ 'end_price' ])) {
            $sku_where[] = [ 'goodsSku.price', '<=', $where[ 'end_price' ] ];
        }

        $query = Goods::alias('goods')->where([['goods.site_id', '=', $this->site_id]])
            ->withSearch(["goods_name", "goods_type", "brand_id", "goods_category", "label_ids", 'service_ids', "sale_num", "status", "memory_group", "condition_grade", "sale_status"], $where)
            ->withJoin(['goodsSku'])->where($sku_where);
        $this->applySaleStateFilter($query, (string)($where['sale_state'] ?? ''));
        if (($where['source'] ?? '') !== '') $query->where('goods.source', '=', (string)$where['source']);
        if (($where['device_color'] ?? '') !== '') $query->where('goods.device_color', '=', (string)$where['device_color']);
        $deviceKeywords = $this->parseDeviceKeywords((string)($where['device_keywords'] ?? ''));
        if ($deviceKeywords) {
            $query->where(function ($child) use ($deviceKeywords) {
                foreach ($deviceKeywords as $index => $keyword) {
                    $method = $index === 0 ? 'where' : 'whereOr';
                    $child->$method('goodsSku.sku_no', 'like', '%' . $keyword . '%');
                }
            });
        }
        $now = time();
        if (($where['start_stock_age'] ?? '') !== '') $query->where('goods.create_time', '<=', $now - (int)$where['start_stock_age'] * 86400);
        if (($where['end_stock_age'] ?? '') !== '') $query->where('goods.create_time', '>=', $now - ((int)$where['end_stock_age'] + 1) * 86400);
        if ($example_goods_ids) $query->whereNotIn('goods.goods_id', $example_goods_ids);
        return $query;
    }

    /**
     * 获取商品排行榜统计类型
     * @return array
     */
    public function getBatchSetDict()
    {
        $list = GoodsDict::getBatchSetDict();
        return $list;
    }

    /**
     * 分类调整时使用（分类登记变化）   数据较多时需优化 Job
     * @return void
     */
    public function batchUpdateCategory($category_id, $old_pid, $new_pid)
    {
        if ((int)$old_pid === (int)$new_pid) return;
        $parents = Db::name('phone_shop_goods_category')->where('site_id', '=', $this->site_id)->column('pid', 'category_id');
        $lineage = static function (int $id, array $tree): array {
            $path = [];
            while ($id > 0) {
                if (!array_key_exists($id, $tree)) throw new AdminException('商品分类上级不存在');
                if (in_array($id, $path, true) || count($path) >= 3) throw new AdminException('分类不能循环或超过三级');
                $path[] = $id;
                $id = (int)$tree[$id];
            }
            return array_reverse($path);
        };
        $oldAncestors = array_diff($lineage((int)$category_id, $parents), [(int)$category_id]);
        $nextParents = $parents;
        $nextParents[(int)$category_id] = (int)$new_pid;
        // 校验整个受影响分支，防止父级移动后其子分类超过三级或形成循环。
        foreach ($parents as $id => $pid) {
            if (in_array((int)$category_id, $lineage((int)$id, $parents), true)) $lineage((int)$id, $nextParents);
        }
        Db::transaction(function () use ($category_id, $oldAncestors, $nextParents, $lineage) {
            $cursor = 0;
            do {
                $rows = (new Goods())->where([['site_id', '=', $this->site_id], ['goods_id', '>', $cursor]])
                    ->withSearch(['goods_category'], ['goods_category' => $category_id])
                    ->field('goods_id,goods_category')->order('goods_id asc')->limit(200)->select()->toArray();
                foreach ($rows as $row) {
                    $cursor = (int)$row['goods_id'];
                    $ids = array_diff(array_map('intval', (array)$row['goods_category']), $oldAncestors);
                    $mapped = [];
                    foreach ($ids as $id) {
                        foreach ($lineage($id, $nextParents) as $ancestor) $mapped[$ancestor] = (string)$ancestor;
                    }
                    (new CoreGoodsChangeLogService())->mutate((int)$this->site_id, [$cursor], 'category', function () use ($cursor, $mapped) {
                        return (new Goods())->where([['site_id', '=', $this->site_id], ['goods_id', '=', $cursor]])
                            ->update(['goods_category' => array_values($mapped)]);
                    });
                }
            } while (count($rows) === 200);
        });
    }

}
