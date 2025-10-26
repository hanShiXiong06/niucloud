<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\admin\goods;

use addon\home_service\app\dict\goods\GoodsDict;
use addon\home_service\app\model\card\MemberCardItem;
use addon\home_service\app\model\goods\Card;
use addon\home_service\app\model\goods\CardSku;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\goods\GoodsSku;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\AdminException;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 商品服务层(项目)
 * Class GoodsService
 * @package addon\home_service\app\service\admin\goods
 */
class GoodsService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Goods();
    }

    /**
     * 获取商品列表(goods)
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'goods_category,additional_manage,grab_orders,goods_id,member_discount,site_id,goods_name,goods_cover,sale_num,status,sort,create_time,price,buy_type,is_force_clock_in,is_force_departure';
        $sku_where = [
            ['goodsSku.is_default', '=', 1],
            ['goods.is_delete', '=', 0],
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
            $order = 'goods.' . $where['order'] . ' ' . $where['sort'] . ',goods.create_time desc';
        } else {
            $order = 'goods.create_time desc';
        }
        $search_model = $this->model->where([['goods.site_id', '=', $this->site_id]])
            ->withSearch(["create_time", "goods_name", "goods_category", "sale_num", "status"], $where)
            ->field($field)
            ->withJoin(
                [
                    'goodsSku' => ['sku_id', 'goods_id', 'price'],
                ])
            ->with(
                [
                    'category'
                ])
            ->where($sku_where)->order($order)->append(['goods_cover_thumb_small', 'buy_type_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商品信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'additional_manage,grab_orders,is_force_clock_in,is_force_departure,goods_id,site_id,goods_name,goods_cover,goods_image,goods_category,sale_num,virtually_sale,status,sort,create_time,update_time,price,price_list,after_sales,buy_type';
        $info = $this->model->field($field)->where([['is_delete', '=', 0], ['goods_id', '=', $id], ['site_id', '=', $this->site_id]])->with('category')->findOrEmpty()->append(['cover_thumb_small', 'image_thumb_small'])->toArray();
        return $info;
    }

    /**
     * 获取商品添加/编辑数据
     * @param array $params
     * @return array
     */
    public function getInit(array $params = [])
    {

        $res = [];
        if (!empty($params['goods_id'])) {
            // 查询商品信息，用于编辑
            $field = 'goods_subtitle,guarantee_id,top_category,is_finish_photograph,additional_manage,grab_orders,is_force_clock_in,is_force_departure,goods_id,site_id,goods_name,goods_cover,goods_image,goods_category,virtually_sale,status,sort,buy_type,after_sales,price_list,goods_content,poster_id,member_discount';
            $goods_info = $this->model->field($field)->where([['goods_id', '=', $params['goods_id']]])->findOrEmpty()->toArray();
            if (!empty($goods_info)) {
                $goods_info['status'] = (string)$goods_info['status'];

                $goods_sku_model = new GoodsSku();

                $sku_field = 'sku_id,sku_name,sku_image,sku_no,goods_id,price,sale_num,is_default,sku_unit,min_buy';
                $sku_order = 'sku_id asc';
                $goods_info['sku_list'] = $goods_sku_model->withSearch(["goods_id"], ['goods_id' => $params['goods_id']])->field($sku_field)->order($sku_order)->select()->toArray();

                $goods_info['spec_type'] = 'single';
                if (count($goods_info['sku_list']) > 1) {
                    // 多规格
                    $goods_info['spec_type'] = 'multi';
                }

                // 海报id，处理数据类型
                if (empty($goods_info['poster_id'])) $goods_info['poster_id'] = '';

                $res['goods_info'] = $goods_info;
            }

        }

        return $res;
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
            $goods_sku_model = new GoodsSku();
            // 商品封面
            if (!empty($data['goods_image'])) $data['goods_cover'] = explode(',', $data['goods_image'])[0];
            $goods_data = [
                'site_id' => $this->site_id,
                'goods_name' => $data['goods_name'],
                'goods_category' => $data['goods_category'],
                'goods_cover' => $data['goods_cover'],
                'goods_image' => $data['goods_image'],
                'status' => $data['status'],
                'buy_type' => $data['buy_type'],
                'price' => $data['price'],
                'sort' => $data['sort'],
                'after_sales' => $data['after_sales'],
                'create_time' => time(),
                'price_list' => $data['price_list'],
                'goods_content' => $data['goods_content'],
                'virtually_sale' => $data['virtually_sale'],
                'poster_id' => $data['poster_id'],
                'member_discount' => $data['buy_type'] == 'buy' ? $data['member_discount'] : '',
                'is_force_clock_in' => $data['is_force_clock_in'] ?? 0,
                'is_force_departure' => $data['is_force_departure'] ?? 0,
                'grab_orders' => $data['grab_orders'],
                'additional_manage' => $data['additional_manage'] ?? "",
                'top_category' => $data['top_category'] ?? 0,
                'is_finish_photograph' => $data['is_finish_photograph'] ?? 0,
                'guarantee_id' => $data['guarantee_id'] ?? '',
                'goods_subtitle' => $data['goods_subtitle']??'',
            ];
            $res = $this->model->create($goods_data);
            // 提前提取常用变量，减少重复调用
            $commonData = [
                'site_id' => $this->site_id,
                'goods_id' => $res->goods_id,
                'sku_no' => $data['sku_no'],
                'sku_unit' => $data['sku_unit'],
                'min_buy' => $data['min_buy'],
            ];
            $defaultSkuName = $data['goods_name'];
            $defaultSkuImage = $data['goods_cover'];
            // 生成单规格SKU数据的方法，减少重复代码
            $generateSingleSku = function ($isDefault = 1, $price = null, $skuName = null, $skuImage = null) use ($commonData, $defaultSkuName, $defaultSkuImage, $data) {
                return array_merge($commonData, [
                    'sku_name' => $skuName ?? $defaultSkuName,
                    'sku_image' => $skuImage ?? $defaultSkuImage,
                    'price' => $price ?? $data['price'],
                    'is_default' => $isDefault,
                ]);
            };
            // 根据不同类型处理SKU数据
            if ($data['buy_type'] == 'reservation' || $data['spec_type'] == 'single') {
                // 预订类型和单规格类型处理逻辑一致，合并判断
                $skuData = $generateSingleSku();
                $goods_sku_model->save($skuData);
            } elseif ($data['spec_type'] == 'multi') {
                // 多规格处理
                // 增加JSON解析错误处理
                $skuList = json_decode($data['goods_sku_data'], true);
                $skuData = [];
                $default_price = 0;
                foreach ($skuList as $item) {
                    $skuData[] = $generateSingleSku(
                        $item['is_default'] ?? 0,
                        $item['price'],
                        $item['sku_name'],
                        $item['sku_image'] ?? null
                    );
                    if ($item['is_default'] == 1){
                        $default_price = $item['price'];
                    }
                }
                $goods_sku_model->saveAll($skuData);
                // 更新商品价格为第一个SKU的价格
                $this->model->where('goods_id', $res->goods_id)->update([
                    'price' => $default_price,
                ]);
            }
            Db::commit();
            return $res->goods_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
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
            $goods_sku_model = new GoodsSku();

            $spec_type = 'single';
            $goods_sku_ids = $goods_sku_model->where([['goods_id', '=', $goods_id]])->column('sku_id');
            $goods_sku_count = count($goods_sku_ids);
            if ($goods_sku_count > 1){
                $spec_type = 'multi';
            }

            if ($data['spec_type'] != $spec_type){
                //检测是否存在次卡
                $card_sku_info = (new CardSku())->where([['goods_id', '=', $goods_id]])->findOrEmpty();
                if (!$card_sku_info->isEmpty()) throw new AdminException('EXIST_NOT_USE_CARD');
            }else{
                // 同类型检查
                if ($data['spec_type'] == 'multi') {
                    $goods_sku_data = json_decode($data['goods_sku_data'], true) ?: [];

                    //检查是否有新增 SKU（没有 sku_id）
                    $hasNewSku = false;
                    foreach ($goods_sku_data as $value) {
                        if (!isset($value['sku_id'])) {
                            $hasNewSku = true;
                            break;
                        }
                    }

                    // 检查是否有旧 SKU 被删除
                    $new_sku_ids = array_column($goods_sku_data, 'sku_id');
                    $deleted_skus = array_diff($goods_sku_ids, $new_sku_ids);
                    $hasDeletedSku = !empty($deleted_skus);

                    if ($hasNewSku || $hasDeletedSku) {
                        //检测是否存在次卡
                        $card_sku_info = (new CardSku())->where([['goods_id', '=', $goods_id]])->findOrEmpty();
                        if (!$card_sku_info->isEmpty()) throw new AdminException('EXIST_NOT_USE_CARD');
                    }
                }
            }


            // 商品封面
            if (!empty($data['goods_image'])) $data['goods_cover'] = explode(',', $data['goods_image'])[0];

            $goods_data = [
                'site_id' => $this->site_id,
                'goods_name' => $data['goods_name'],
                'goods_category' => $data['goods_category'],
                'goods_cover' => $data['goods_cover'],
                'goods_image' => $data['goods_image'],
                'status' => $data['status'],
                'sort' => $data['sort'],
                'buy_type' => $data['buy_type'],
                'price' => $data['price'],
                'after_sales' => $data['after_sales'],
                'price_list' => $data['price_list'],
                'goods_content' => $data['goods_content'],
                'virtually_sale' => $data['virtually_sale'],
                'poster_id' => $data['poster_id'],
                'member_discount' => $data['buy_type'] == 'buy' ? $data['member_discount'] : '',
                'update_time' => time(),
                'is_force_clock_in' => $data['is_force_clock_in'] ?? 0,
                'is_force_departure' => $data['is_force_departure'] ?? 0,
                'grab_orders' => $data['grab_orders'],
                'additional_manage' => $data['additional_manage'] ?? "",
                'top_category' => $data['top_category'] ?? 0,
                'is_finish_photograph' => $data['is_finish_photograph'] ?? 0,
                'guarantee_id' => $data['guarantee_id'] ?? '',
                'goods_subtitle' => $data['goods_subtitle']??'',
            ];
            $this->model->where([['goods_id', '=', $goods_id], ['site_id', '=', $this->site_id]])->update($goods_data);

            $card_sku_model = (new CardSku());
            $card_model = (new Card());
            if ($data['buy_type'] == 'reservation') {
                // 单规格
                $sku_data = [
                    'site_id' => $this->site_id,
                    'sku_name' => $data['goods_name'],
                    'sku_image' => $data['goods_cover'],
                    'sku_no' => $data['sku_no'],
                    'goods_id' => $goods_id,
                    'price' => $data['price'],
                    'sku_unit' => $data['sku_unit'],
                    'min_buy' => $data['min_buy'],
                    'is_default' => 1
                ];

                $sku_count = $goods_sku_model->where([['goods_id', '=', $goods_id]])->count();
                if ($sku_count > 1) {

                    // 规格项发生变化，删除旧规格，添加新规格重新生成
                    $goods_sku_model->where([['goods_id', '=', $goods_id]])->delete();

                    // 新增规格
                    $goods_sku_model->create($sku_data);

                } else {

                    $goods_sku_model->where([['goods_id', '=', $goods_id]])->update($sku_data);
                    //同步次卡套餐
                    $card_sku_list = $card_sku_model->where([['goods_id', '=', $goods_id]])->select()->toArray();
                    if (!empty($card_sku_list)){
                        foreach($card_sku_list as $value){
                            $card_sku_model->where([['sku_id', '=', $value['sku_id']]])->update([
                                'sku_name' => $sku_data['sku_name'],
                                'sku_image' => $sku_data['sku_image'],
                                'original_price' => $sku_data['price'],
                                'sku_unit' => $sku_data['sku_unit'],
                                'goods_name' => $goods_data['goods_name'],
                            ]);
                            $card_original_price = $sku_data['price'] * $value['max_use_times'];
                            $old_price = $value['original_price'] * $value['max_use_times'];
                            $diff = $card_original_price - $old_price;
                            $card_model->where([['card_id', '=', $value['card_id']]])->inc('original_price', $diff)->update();
                        }
                    }
                }

            } else {

                if ($data['spec_type'] == 'single') {
                    // 单规格
                    $sku_data = [
                        'site_id' => $this->site_id,
                        'sku_name' => $data['goods_name'],
                        'sku_image' => $data['goods_cover'],
                        'sku_no' => '',
                        'goods_id' => $goods_id,
                        'price' => $data['price'],
                        'sku_unit' => $data['sku_unit'],
                        'min_buy' => $data['min_buy'] ?? 1,
                        'is_default' => 1
                    ];
                    $sku_count = $goods_sku_model->where([['goods_id', '=', $goods_id]])->count();
                    if ($sku_count > 1) {

                        // 规格项发生变化，删除旧规格，添加新规格重新生成
                        $goods_sku_model->where([['goods_id', '=', $goods_id]])->delete();

                        // 新增规格
                        $goods_sku_model->create($sku_data);

                    } else {

                        $goods_sku_model->where([['goods_id', '=', $goods_id]])->update($sku_data);

                        //同步次卡套餐
                        $card_sku_list = $card_sku_model->where([['goods_id', '=', $goods_id]])->select()->toArray();
                        if (!empty($card_sku_list)){
                            foreach($card_sku_list as $value){
                                $card_sku_model->where([['sku_id', '=', $value['sku_id']]])->update([
                                    'sku_name' => $sku_data['sku_name'],
                                    'sku_image' => $sku_data['sku_image'],
                                    'original_price' => $sku_data['price'],
                                    'sku_unit' => $sku_data['sku_unit'],
                                    'goods_name' => $goods_data['goods_name'],
                                ]);
                                $card_original_price = $sku_data['price'] * $value['max_use_times'];
                                $old_price = $value['original_price'] * $value['max_use_times'];
                                $diff = $card_original_price - $old_price;
                                $card_model->where([['card_id', '=', $value['card_id']]])->inc('original_price', $diff)->update();
                            }
                        }

                    }
                } elseif ($data['spec_type'] == 'multi') {

                    $data['goods_sku_data'] = json_decode($data['goods_sku_data'], true);
                    // 多规格数据
                    $first_sku_data = reset($data['goods_sku_data']);

                    // 检测规格项是否发生变化
                    if (!empty($first_sku_data['sku_id'])) {

                        // 规格项没有变化，修改/新增规格数据

                        $sku_ids = $goods_sku_model->where([['goods_id', '=', $goods_id]])->column('sku_id');

                        $sku_id_arr = [];
                        $default_price = 0;
                        foreach ($data['goods_sku_data'] as $k => $v) {
                            $sku_data = [
                                'site_id' => $this->site_id,
                                'sku_name' => $v['sku_name'],
                                'sku_image' => !empty($v['sku_image']) ? $v['sku_image'] : $data['goods_cover'],
                                'sku_no' => $v['sku_no'] ?? '',
                                'goods_id' => $goods_id,
                                'price' => $v['price'],
                                'is_default' => $v['is_default'],
                                'sku_unit' => $v['sku_unit'],
                                'min_buy' => $v['min_buy']  ?? 1,
                            ];

                            if ($v['is_default'] == 1){
                                $default_price = $v['price'];
                            }

                            if (!empty($v['sku_id'])) {
                                // 修改规格
                                $sku_id_arr[] = $v['sku_id'];
                                $goods_sku_model->where([['sku_id', '=', $v['sku_id']], ['goods_id', '=', $goods_id]])->update($sku_data);

                                //同步次卡套餐
                                $card_sku_list = $card_sku_model->where([['goods_sku_id', '=', $v['sku_id']],['goods_id', '=', $goods_id]])->select()->toArray();
                                if (!empty($card_sku_list)){
                                    foreach($card_sku_list as $value){
                                        $card_sku_model->where([['sku_id', '=', $value['sku_id']]])->update([
                                            'sku_name' => $sku_data['sku_name'],
                                            'sku_image' => $sku_data['sku_image'],
                                            'original_price' => $sku_data['price'],
                                            'sku_unit' => $sku_data['sku_unit'],
                                            'goods_name' => $goods_data['goods_name'],
                                        ]);
                                        $card_original_price = $sku_data['price'] * $value['max_use_times'];
                                        $old_price = $value['original_price'] * $value['max_use_times'];
                                        $diff = $card_original_price - $old_price;
                                        $card_model->where([['card_id', '=', $value['card_id']]])->inc('original_price', $diff)->update();
                                    }
                                }
                            } else {
                                // 新增规格
                                $sku_model = $goods_sku_model->create($sku_data);
                                $sku_id_arr[] = $sku_model->sku_id;
                            }
                        }

                        foreach ($sku_ids as $k => $v) {
                            if (in_array($v, $sku_id_arr)) unset($sku_ids[$k]);
                        }

                        if (!empty($sku_ids)) {
                            $goods_sku_model->where([['sku_id', 'in', implode(',', $sku_ids)]])->delete();
                        }


                        $this->model->where([['goods_id', '=', $goods_id]])->update([
                            'price' => $default_price ?? 0,
                        ]);

                    } else {

                        // 规格项发生变化，删除旧规格，添加新规格重新生成
                        $goods_sku_model->where([['goods_id', '=', $goods_id]])->delete();

                        $sku_data = [];
                        $default_price = 0;
                        foreach ($data['goods_sku_data'] as $k => $v) {
                            $sku_data[] = [
                                'site_id' => $this->site_id,
                                'sku_name' => $v['sku_name'],
                                'sku_image' => !empty($v['sku_image']) ? $v['sku_image'] : $data['goods_cover'],
                                'sku_no' => $v['sku_no'],
                                'goods_id' => $goods_id,
                                'price' => $v['price'],
                                'is_default' => $v['is_default'],
                                'sku_unit' => $v['sku_unit'],
                                'min_buy' => $v['min_buy'],
                            ];

                            if ($v['is_default'] == 1){
                                $default_price = $v['price'];
                            }
                        }
                        $goods_sku_model->saveAll($sku_data);
                        $this->model->where([['goods_id', '=', $goods_id]])->update([
                            'price' => $default_price ?? 0,
                        ]);
                    }

                }
            }

            Db::commit();
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
        try {
            Db::startTrans();

            $goods_info = $this->model->where([
                ['goods_id', '=', $params['goods_id']],
                ['site_id', '=', $this->site_id]
            ])->field('goods_id,buy_type')->findOrEmpty()->toArray();

            if (empty($goods_info)) {
                throw new CommonException('O2O_GOODS_NOT_EXIST');
            }

            if ($goods_info['buy_type'] != GoodsDict::BUY) {
                throw new CommonException('O2O_GOODS_NOT_SET_MEMBER_PRICE');
            }

            // 修改商品的会员等级折扣
            $this->model->where([
                ['goods_id', '=', $params['goods_id']],
                ['site_id', '=', $this->site_id]
            ])->update([
                'member_discount' => $params['member_discount']
            ]);

            $sku_list = $params['sku_list'];
            if (!empty($sku_list)) {
                $goods_sku_model = new GoodsSku();
                foreach ($sku_list as $k => $v) {
                    $update_data = [
                        'member_price' => json_encode($v['member_price']),
                    ];

                    $goods_sku_model->where([
                        ['goods_id', '=', $params['goods_id']],
                        ['sku_id', '=', $v['sku_id']]
                    ])->update($update_data);
                }
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage() . '，Line：' . $e->getLine() . '，File：' . $e->getFile());
        }
    }

    /**
     * 复制服务项目
     * @param int $goods_id
     * @return mixed
     */
    public function copy(int $goods_id)
    {
        try {
            Db::startTrans();
            $goods_sku_model = new GoodsSku();
            // 查询商品信息
            $goods_data = $this->model->where([['goods_id', '=', $goods_id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
            if (empty($goods_data)) {
                throw new AdminException('HOME_SERVICE_GOODS_NOT_EXIST');
            }
            // 初始化数据
            unset($goods_data['goods_id'],$goods_data['update_time'],$goods_data['is_delete'],$goods_data['delete_time']);
            $goods_data['goods_name'] .= '_副本';
            $goods_data['sale_num'] = 0;
            $goods_data['create_time'] = time();
            $goods_data['sort'] = 0;
            $goods_data['status'] = 0;

            // 添加商品
            $res = $this->model->create($goods_data);


            // 查询商品规格信息
            $sku_field = 'site_id,sku_name,sku_image,sku_no,goods_id,price,market_price,sale_num,sku_unit,min_buy,is_default,member_price';

            $sku_order = 'sku_id asc';
            $goods_sku_list = $goods_sku_model->withSearch(["goods_id"], ['goods_id' => $goods_id])->field($sku_field)->order($sku_order)->select()->toArray();
            // 添加商品规格
            foreach ($goods_sku_list as $k => $v) {
                $goods_sku_list[$k]['sale_num'] = 0;
                $goods_sku_list[$k]['goods_id'] = $res->goods_id;
            }
            $goods_sku_model->saveAll($goods_sku_list);

            Db::commit();
            return $res->goods_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 删除商品
     * @param int $id
     * @return bool
     */
    public function del($data)
    {
        $goods_ids = $data['goods_ids'];
        //检测是否存在未使用的次卡
        $member_card_count = (new MemberCardItem())->where([['goods_id', 'in', $goods_ids]])->whereRaw('use_num < num')->count();
        if ($member_card_count > 0) throw new AdminException('EXIST_NOT_USE_CARD');

        $update = ['status' => 0, 'delete_time' => time(), 'is_delete' => 1];
        return $this->model->where([['goods_id', 'in', $goods_ids], ['site_id', '=', $this->site_id]])->update($update);
    }


    /**
     * 查询商品SKU规格列表
     * @param $params
     * @return array
     */
    public function getSkuList($params)
    {
        $goods_sku_model = new GoodsSku();

        $field = 'sku_id, sku_name, sku_image,sku_no,goods_id,price,market_price,sale_num,sku_unit,min_buy,is_default,member_price';
        $order = 'sku_id asc';
        $list = $goods_sku_model->where([['site_id', '=', $this->site_id]])->withSearch(["goods_id"], ['goods_id' => $params['goods_id']])->with(['goods'])->field($field)->order($order)->select()->toArray();
        return $list;
    }

    /**
     * 获取项目列表不分页
     * @param array $where
     * @return array
     */
    public function getLists(array $where = [])
    {
        $field = 'additional_manage,grab_orders,is_force_clock_in,is_force_departure,goods_id,site_id,goods_name,goods_cover,goods_image,goods_category,sale_num,virtually_sale,status,sort,create_time,update_time,price,price_list,after_sales,buy_type';
        $order = 'create_time desc';
        $list = $this->model->where([['site_id', '=', $this->site_id], ['is_delete', '=', 0]])->withSearch(["goods_name", "create_time", "categroy_id"], $where)->field($field)->with('category')->order($order)->append(['cover_thumb_small'])->select()->toArray();
        return $list;
    }

    /**
     * 项目上下架
     */
    public function editStatus($goods_ids, $status)
    {
        $this->model->where([['goods_id', 'in', $goods_ids], ['site_id', '=', $this->site_id]])->update(['status' => $status]);
        return true;
    }

    /**
     * 修改排序
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function editSort(int $id, array $data)
    {
        $this->model->where([['goods_id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 获取商品选择分页列表
     * @param array $where
     * @return array
     */
    public function getSelectPage(array $where = [])
    {
        $field = 'goods_id,member_discount,site_id,goods_name,goods_cover,sale_num,status,sort,create_time,price,buy_type';
        $sku_where = [
            ['goodsSku.is_default', '=', 1],
            ['goods.is_delete', '=', 0],
            ['goods.status', '=', 1],
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

        if (!empty($where['goods_ids'])) {
            $sku_where[] = ['goods.goods_id', 'in', $where['goods_ids']];
        }

        if (!empty($where['keyword'])) {
            $sku_where[] = ['goods.goods_name', 'like', '%' . $where['keyword'] . '%' ];
        }

        if (!empty($where['order'])) {
            $order = 'goods.' . $where['order'] . ' ' . $where['sort'];
        } else {
            $order = 'goods.sort desc,goods.create_time desc';
        }

        $verify_goods_ids = [];

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where['verify_goods_ids'])) {
            $verify_goods_ids = $this->model->where([
                ['goods_id', 'in', $where['verify_goods_ids']]
            ])->field('goods_id')->select()->toArray();
        }

        $search_model = $this->model->where([['goods.site_id', '=', $this->site_id]])->withSearch(["create_time", "goods_name", "goods_category"], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => ['sku_id', 'goods_id', 'price'],
            ])
            ->with([
                'skuList'
            ])->where($sku_where)->order($order)->append(['goods_cover_thumb_small', 'buy_type_name']);
        $list = $this->pageQuery($search_model);

        if (!empty($verify_goods_ids)) {
            $verify_goods_ids = array_column($verify_goods_ids, 'goods_id');
        }
        $list['verify_goods_ids'] = $verify_goods_ids;

        return $list;
    }

    /**
     * 获取商品选择分页列表
     * @param array $where
     * @return array
     */
    public function getSelectSku(array $where = [])
    {
        $field = 'site_id, goods_id, goods_name, goods_cover';
        $order = 'sort desc,create_time desc';

        $select_goods_list = [];// 已选商品列表

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where['verify_goods_ids'])) {
            $verify_goods_ids = $this->model->where([
                ['goods_id', 'in', $where['verify_goods_ids']],
                ['status', '=', 1]
            ])->field('goods_id')->select()->toArray();

            if (!empty($verify_goods_ids)) {
                $verify_goods_ids = array_column($verify_goods_ids, 'goods_id');
            }

            $select_goods_list = $this->model
                ->field($field)
                ->withJoin([
                    'goodsSku' => ['sku_id', 'sku_name', 'goods_id', 'price'],
                ])
                ->with([
                    'skuList'
                ])
                ->where([
                    ['goods.goods_id', 'in', $verify_goods_ids]
                ])
                ->order($order)->append(['goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid'])
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
        $field = 'site_id, goods_id, goods_name, goods_cover,goods_image,goods_subtitle,goods_content,member_discount';
        $order = 'sort desc,create_time desc';

        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goods.site_id', '=', $this->site_id ],
            [ 'status', '=', 1 ],
        ];

        if (!empty($where[ 'keyword' ])) {
            $sku_where[] = [ 'goods_name|goods_subtitle', 'like', '%' . $where[ 'keyword' ] . '%' ];
        }

        $search_model = $this->model
            ->withSearch([ "goods_category" ], $where)
            ->field($field)
            ->withJoin([
                'goodsSku' => [ 'sku_id', 'sku_name', 'goods_id', 'price', 'market_price', 'member_price' ],
            ])
            ->where($sku_where)->order($order)->append(['goods_cover_thumb_small', 'goods_cover_thumb_mid' ]);
        $list = $this->pageQuery($search_model);
        if (!empty($where[ 'member_id' ])) {
            $member_info = $this->getMemberInfo($where[ 'member_id' ]);
            foreach ($list[ 'data' ] as $k => &$v) {
                if (!empty($v[ 'goodsSku' ])) {
                    $v[ 'goodsSku' ][ 'member_price' ] = $this->getMemberPrice($member_info, $v[ 'member_discount' ], $v[ 'goodsSku' ][ 'member_price' ], $v[ 'goodsSku' ][ 'price' ]);
                }
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

        $field = 'site_id,sku_id, sku_name, sku_image, sku_no, goods_id, price, market_price, sale_num, is_default,member_price';

        $goods_sku_model = new GoodsSku();

        $info = $goods_sku_model->where([ [ 'site_id', '=', $this->site_id ], [ 'sku_id', '=', $data[ 'sku_id' ] ] ])
            ->field($field)
            ->with([
                // 商品主表
                'goods' => function ($query) {
                    $query->withField('goods_id, goods_name, goods_subtitle, goods_cover, sale_num, status,member_discount')
                        ->append(['goods_cover_thumb_small', 'goods_cover_thumb_mid', 'goods_cover_thumb_big' ]);
                },
                // 商品规格列表
                'skuList' => function ($query) {
                    $query->field('site_id, sku_id, sku_name, sku_image, sku_no, goods_id, price, market_price, is_default,member_price');
                },
            ])
            ->append([ 'sku_image_thumb_small', 'sku_image_thumb_mid', 'sku_image_thumb_big' ])
            ->findOrEmpty()->toArray();
        if (!empty($info) && !empty($data[ 'member_id' ])) {
            $member_info = $this->getMemberInfo($data[ 'member_id' ]);

            $info[ 'member_price' ] = $this->getMemberPrice($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'member_price' ], $info[ 'price' ]);

            foreach ($info['skuList'] as $k => &$v) {
                $v['member_price'] = $this->getMemberPrice($member_info, $info[ 'goods' ][ 'member_discount' ], $v['member_price'], $v['price']);
            }

        }

        return $info;
    }

    public function getMemberInfo($member_id)
    {
        $member_model = new Member();
        $member_field = 'member_level';
        $member_info = $member_model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $member_id]
        ])->field($member_field)
            ->with([
                // 会员等级
                'memberLevelData' => function ($query) {
                    $query->field('level_id, site_id, level_name, status, level_benefits, level_gifts');
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

        // 根据员折扣类型计算价格
        if ($member_discount === 'discount') {
            // 按照会员等级折扣计算
            $levelBenefits = $member_info['memberLevelData']['level_benefits'] ?? [];
            if (!empty($levelBenefits['discount']['is_use'])) {
                $discount = $levelBenefits['discount']['discount'] ?? 10; // 默认不打折
                $price = $price * $discount / 10;
            }
        } elseif ($member_discount === 'fixed_price') {
            // 指定会员价
            if (!empty($member_price)) {
                $memberPriceData = is_array($member_price) ? $member_price : json_decode($member_price, true);
                // 验证解析结果是否为数组
                if (is_array($memberPriceData)) {
                    $levelKey = 'level_' . $member_info['member_level'];
                    if (!empty($memberPriceData[$levelKey])) {
                        $price = $memberPriceData[$levelKey];
                    }
                }
            }
        }
        return number_format($price, 2, '.', '');
    }
}
