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
use addon\home_service\app\model\goods\Card;
use addon\home_service\app\model\goods\CardSku;
use core\base\BaseAdminService;
use core\exception\AdminException;
use core\exception\CommonException;
use think\facade\Db;
use  addon\home_service\app\service\core\goods\CoreCardService;
use think\Model;


/**
 * 次卡次卡服务层(项目)
 * Class GoodsService
 * @package addon\home_service\app\service\admin\goods
 */
class CardService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Card();
    }


    public function getValidType()
    {
        return (new CoreCardService)->getValidType();
    }


    /**
     * 获取次卡列表(goods)
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'card_image,status,card_name,card_id,site_id,card_cover,sort,sale_num,virtually_sale,price,original_price,total_times,valid_type';
        $sku_where = [
            ['is_delete', '=', 0],  // 主表条件用别名card
        ];
        $order = 'create_time desc';
        if (!empty($where['order'])) {
            $order = $where['order'] . ' ' . $where['sort'];
        }
        $search_model = $this->model
            ->where([['card.site_id', '=', $this->site_id]])  // 主表条件用别名
            ->withSearch(["create_time", "card_name", "sale_num", "status", "valid_type"], $where)
            ->field($field)
            ->withJoin([
                'cardSku' => ['sku_id', 'goods_id', 'price'],
            ], 'left')
            ->with([
                'skuList' => function($query){
                    $query->append(['card_sku_image_thumb_mid']);
                }
            ])
            ->where($sku_where)
            ->order($order)
            ->group('card.card_id')
            ->hidden(['cardSku'])
            ->append(['card_cover_thumb_small']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取次卡信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $card_id)
    {
        $field = 'card_name,card_id,site_id,card_content,sort,sale_num,virtually_sale,price,original_price,total_times,valid_type';
        $info = $this->model->field($field)->where([['card_id', '=', $card_id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 获取次卡添加/编辑数据
     * @param array $params
     * @return array
     */
    public function getInit(array $params = [])
    {

        $res = [];
        if (!empty($params['card_id'])) {
            // 查询次卡信息，用于编辑
            $field = 'guarantee_id,card_image,card_name,card_id,site_id,card_content,sort,sale_num,virtually_sale,price,original_price,total_times,valid_type,status,poster_id,member_discount';
            $goods_info = $this->model->field($field)->where([['card_id', '=', $params['card_id']]])->findOrEmpty()->toArray();
            if (!empty($goods_info)) {
                $goods_info['status'] = (string)$goods_info['status'];
                $card_sku_model = new CardSku();
                $sku_field = 'goods_name,sku_id,site_id,sku_name,sku_image,card_id,price,original_price,goods_id,goods_sku_id,max_use_times,sale_num,sku_unit';
                $sku_order = 'sku_id asc';
                $goods_info['sku_list'] = $card_sku_model->withSearch(["card_id"], ['card_id' => $params['card_id']])->field($sku_field)->order($sku_order)->select()->toArray();
                // 海报id，处理数据类型
                if (empty($goods_info['poster_id'])) $goods_info['poster_id'] = '';
                $res['goods_info'] = $goods_info;
            }
        }

        return $res;
    }


    /**
     * 添加次卡
     * @param array $data
     * @return mixed
     * @throws CommonException
     */
    public function add(array $data)
    {
        try {
            Db::startTrans();
            // 统一验证数据（包括SKU解析）
            $validated = $this->validateData($data);
            // 构建主数据（使用验证后的数据）
            $goodsData = $this->buildGoodsData($validated, $validated['sku_data'], true);
            $res = $this->model->create($goodsData);
            if (!$res) {
                throw new CommonException('HOME_SERVICE_CARD_CREATE_FAILED');
            }
            // 处理SKU
            $this->handleSkuData($validated['sku_data'], $res->card_id);
            Db::commit();
            return $res->card_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 次卡编辑
     * @param int $cardId
     * @param array $data
     * @return bool
     * @throws CommonException
     */
    public function edit(int $cardId, array $data)
    {
        try {
            Db::startTrans();
            // 统一验证数据（包括SKU解析）
            $validated = $this->validateData($data);
            // 构建主数据并更新
            $goodsData = $this->buildGoodsData($validated, $validated['sku_data'], false);
            if (!$this->model->where(['card_id' => $cardId, 'site_id' => $this->site_id])->update($goodsData)) {
                throw new CommonException('HOME_SERVICE_CARD_UPDATE_FAILED');
            }
            // 处理SKU
            (new CardSku())->where(['card_id' => $cardId, 'site_id' => $this->site_id])->delete();
            $this->handleSkuData($validated['sku_data'], $cardId);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 统一验证并预处理数据（包括封面图和SKU）
     * @param array $data
     * @return array 处理后的有效数据
     * @throws CommonException
     */
    private function validateData(array $data): array
    {
        // 1. 处理封面图
        $goodsCover = '';
        if (!empty($data['card_image'])) {
            $images = explode(',', $data['card_image']);
            $goodsCover = $images[0] ?? '';
        }
        // 2. 解析并验证SKU数据
        if (empty($data['goods_sku_data'])) {
            throw new CommonException('HOME_SERVICE_SKU_DATA_EMPTY');
        }
        $skuData = json_decode($data['goods_sku_data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CommonException('HOME_SERVICE_SKU_DATA_FORMAT_ERROR');
        }
        if (!is_array($skuData)) {
            throw new CommonException('HOME_SERVICE_SKU_DATA_MUST_BE_ARRAY');
        }
        // 返回处理后的有效数据
        return array_merge($data, [
            'card_cover' => $goodsCover,
            'sku_data' => $skuData
        ]);
    }

    /**
     * 构建商品主数据（仅负责组装，不做验证）
     * @param array $data 已验证的数据
     * @param array $skuData 已验证的SKU数据
     * @param bool $isCreate
     * @return array
     */
    private function buildGoodsData(array $data, array $skuData, bool $isCreate): array
    {
        // 新增：计算 单价×数量 的总和
        $calculateTotal = function () use ($skuData) {
            $total = 0;
            $original_total = 0;
            foreach ($skuData as $sku) {
                // 确保 price 和 max_use_times 都是数字（处理字符串格式的数字）
                $price = is_numeric($sku['price']) ? (float)$sku['price'] : 0;
                $original_price = is_numeric($sku['original_price']) ? (float)$sku['original_price'] : 0;
                $times = is_numeric($sku['max_use_times']) ? (int)$sku['max_use_times'] : 0;
                $total += $price * $times;
                $original_total += $original_price * $times;
            }
            return [
                'total' => $total,
                'original_total' => $original_total,
            ];
        };
        // 保留原有的其他字段累加逻辑（如原价）
        $sum = function ($field) use ($skuData) {
            return array_sum(array_filter(array_column($skuData, $field), 'is_numeric'));
        };
        return [
            'site_id' => $this->site_id,
            'card_name' => $data['card_name'] ?? '',
            'card_image' => $data['card_image'] ?? '',
            'status' => $data['status'] ?? 0,
            'price' => $calculateTotal()['total'], // 改为使用新的计算逻辑
            'original_price' => $calculateTotal()['original_total'], // 原价逻辑不变（如果需要也可以改为 ×数量，按需调整）
            'total_times' => $sum('max_use_times'),
            'sort' => $data['sort'] ?? 0,
            $isCreate ? 'create_time' : 'update_time' => time(),
            'card_content' => $data['card_content'] ?? '',
            'virtually_sale' => $data['virtually_sale'] ?? 0,
            'poster_id' => $data['poster_id'] ?? 0,
            'valid_type' => $data['valid_type'] ?? '',
            'guarantee_id' => $data['guarantee_id'] ?? 0,
            'member_discount' => $data['member_discount'] ?? 0,
        ];
    }

    /**
     * 处理SKU数据
     * @param array $skuData
     * @param int $cardId
     * @throws CommonException
     */
    private function handleSkuData(array $skuData, int $cardId): void
    {
        if (empty($skuData)) return;
        $cardSkuModel = new CardSku();
        $saveData = array_map(function ($item) use ($cardId) {
            return array_merge($item, [
                'site_id' => $this->site_id,
                'card_id' => $cardId
            ]);
        }, $skuData);
        if (!$cardSkuModel->saveAll($saveData)) {
            throw new CommonException('SKU_DATA_SAVE_FAILED');
        }
    }


    /**
     * 编辑次卡规格列表会员价格
     * @param $params
     * @return array|bool
     */
    public function editCardListMemberPrice($params)
    {
        try {
            Db::startTrans();
            $goods_info = $this->model->where([
                ['card_id', '=', $params['card_id']],
                ['site_id', '=', $this->site_id]
            ])->field('card_id')->findOrEmpty()->toArray();
            if (empty($goods_info)) {
                throw new CommonException('HOME_SERVICE_CARD_NOT_EXIST');
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage() . '，Line：' . $e->getLine() . '，File：' . $e->getFile());
        }
    }


    /**
     * 删除次卡
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        return $this->model->where([['card_id', '=', $id], ['site_id', '=', $this->site_id]])->update(['is_delete' => 1]);
    }


    /**
     * 查询次卡SKU规格列表
     * @param $params
     * @return array
     */
    public function getSkuList($params)
    {
        $card_sku_model = new CardSku();
        $sku_field = 'sku_id,site_id,sku_name,sku_image,card_id,price,original_price,goods_id,goods_sku_id,max_use_times,sale_num,sku_unit';
        $order = 'sku_id asc';
        $list = $card_sku_model->where([['site_id', '=', $this->site_id]])->withSearch(["card_id"], ['card_id' => $params['card_id']])->with(['card'])->field($sku_field)->order($order)->select()->toArray();
        return $list;
    }

    /**
     * 获取项目列表不分页
     * @param array $where
     * @return array
     */
    public function getLists(array $where = [])
    {
        $field = 'additional_manage,grab_orders,is_force_clock_in,is_force_departure,goods_id,site_id,goods_name,card_content,goods_image,goods_category,sale_num,virtually_sale,status,sort,create_time,update_time,price,price_list,after_sales,buy_type';
        $order = 'create_time desc';
        $list = $this->model->where([['site_id', '=', $this->site_id], ['is_delete', '=', 0]])->withSearch(["goods_name", "create_time", "categroy_id"], $where)->field($field)->with('category')->order($order)->append(['cover_thumb_small'])->select()->toArray();
        return $list;
    }

    /**
     * 项目上下架
     */
    public function editStatus($id, $data)
    {
        $this->model->where([['card_id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
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
        $this->model->where([['card_id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 获取商品选择分页列表
     * @param array $where
     * @return array
     */
    public function getSelectPage(array $where = [])
    {
        $field = 'card_id,card_image,member_discount,site_id,card_name,card_cover,sale_num,status,sort,create_time,price';
        $sku_where = [
            ['is_delete', '=', 0],
        ];

        if (!empty($where['card_ids'])) {
            $sku_where[] = ['card.card_id', 'in', $where['card_ids']];
        }

        $order = 'sort desc,create_time desc';

        $verify_card_ids = [];

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where['verify_card_ids'])) {
            $verify_card_ids = $this->model->where([
                ['card_id', 'in', $where['verify_card_ids']]
            ])->field('card_id')->select()->toArray();
        }

        $search_model = $this->model->where([['card.site_id', '=', $this->site_id]])
            ->withSearch(["create_time", "card_name"], $where)
            ->field($field)
            ->withJoin([
                'cardSku' => ['sku_id', 'card_id', 'price'],
            ])
            ->with([
                'skuList'
            ])->where($sku_where)->group('card.card_id')->order($order)->append(['card_cover_thumb_small']);
        $list = $this->pageQuery($search_model);

        if (!empty($verify_card_ids)) {
            $verify_card_ids = array_column($verify_card_ids, 'card_id');
        }
        $list['verify_card_ids'] = $verify_card_ids;

        return $list;
    }

    /**
     * 获取商品选择分页列表
     * @param array $where
     * @return array
     */
    public function getSelectSku(array $where = [])
    {
        $field = 'site_id, card_id, card_name, card_cover';
        $order = 'sort desc,create_time desc';

        $select_goods_list = [];// 已选商品列表

        // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        if (!empty($where['verify_card_ids'])) {
            $verify_goods_ids = $this->model->where([
                ['card_id', 'in', $where['verify_card_ids']],
                ['status', '=', 1]
            ])->field('card_id')->select()->toArray();

            if (!empty($verify_goods_ids)) {
                $verify_goods_ids = array_column($verify_goods_ids, 'card_id');
            }

            $select_goods_list = $this->model
                ->field($field)
                ->withJoin([
                    'cardSku' => ['sku_id', 'goods_id', 'price'],
                ], 'left')
                ->with([
                    'skuList'
                ])
                ->where([
                    ['card.card_id', 'in', $verify_goods_ids]
                ])
                ->group('card.card_id')
                ->order($order)->append(['card_cover_thumb_small', 'card_cover_thumb_mid'])
                ->select()->toArray();
        }

        return $select_goods_list;
    }

}
