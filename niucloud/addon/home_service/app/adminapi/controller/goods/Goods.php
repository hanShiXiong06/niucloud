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

namespace addon\home_service\app\adminapi\controller\goods;

use core\base\BaseAdminController;
use addon\home_service\app\service\admin\goods\GoodsService;


/**
 * 服务项目控制器
 * @description 服务项目
 * Class Goods
 * @package addon\home_service\app\adminapi\controller\goods
 */
class Goods extends BaseAdminController
{
    /**
     * 获取服务项目列表
     * @description 获取服务项目列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["goods_name", ""],
            ["goods_category", ""],
            ["start_sale_num", ""],
            ["end_sale_num", ""],
            ["start_price", ""],
            ["end_price", ""],
            ["status", ""],
            ['order', ''],
            ['sort', ''],
            ['create_time', []],
        ]);
        return success((new GoodsService())->getPage($data));
    }

    /**
     * 服务项目详情
     * @description 服务项目详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new GoodsService())->getInfo($id));
    }

    /**
     * 获取商品添加/编辑数据
     * @description 获取商品添加/编辑初始化数据
     * @return \think\Response
     */
    public function init()
    {
        $data = $this->request->params([
            ["goods_id", 0],
        ]);
        return success((new GoodsService())->getInit($data));
    }

    /**
     * 添加服务项目
     * @description 添加服务项目
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["goods_name", ""],
            ["goods_subtitle", ""],
            ["goods_image", ""],
            ["goods_cover", ""],
            ["goods_category", ''],
            ["status", 0],
            ["sort", 0],
            ["buy_type", ''],
            ["after_sales", 0],
            ['spec_type', ''],// 规格类型，single：单规格，multi：多规格
            ["price", 0],
            ["market_price", 0],
            ["sku_no", ''],
            ["sku_unit", ""],
            ["virtually_sale", 0],
            ["sku_unit", ''],
            ["price_list", ''],
            ["goods_sku_data", ''],
            ["min_buy", 1],
            ["goods_content", ''],
            ['poster_id', 0],
            ['member_discount', ''], // 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
            ["is_force_clock_in", 1],
            ["is_force_departure", 1],
            ["grab_orders", 1],
            ["additional_manage", ''],
            ["top_category", 0],
            ["is_finish_photograph", 0],
            ['guarantee_id','']
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Goods.add');
        $id = (new GoodsService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 服务项目编辑
     * @description 服务项目编辑
     * @param $id 服务项目id
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["goods_name", ""],
            ["goods_subtitle", ""],
            ["goods_image", ""],
            ["goods_cover", ""],
            ["goods_category", ''],
            ["status", 0],
            ["sort", 0],
            ["buy_type", ''],
            ["after_sales", 0],
            ['spec_type', ''],// 规格类型，single：单规格，multi：多规格
            ["price", 0],
            ["market_price", 0],
            ["sku_no", ''],
            ["virtually_sale", 0],
            ["sku_unit", ''],
            ["price_list", ''],
            ["goods_sku_data", ''],
            ["min_buy", 1],
            ["goods_content", ''],
            ['poster_id', 0],
            ['member_discount', ''], // 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
            ["is_force_clock_in", 1],
            ["is_force_departure", 1],
            ["grab_orders", 1],
            ["additional_manage", ''],
            ["top_category", 0],
            ["is_finish_photograph", 0],
                ['guarantee_id','']
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Goods.edit');
        (new GoodsService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 编辑商品规格列表会员价格
     * @description 编辑商品规格列表会员价格
     * @return \think\Response
     */
    public function editGoodsListMemberPrice()
    {
        $data = $this->request->params([
            ['goods_id', 0],
            ['member_discount', ''], // 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
            ['sku_list', '']
        ]);
        (new GoodsService())->editGoodsListMemberPrice($data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 复制服务项目
     * @description 复制服务项目
     * @param int $id
     * @return \think\Response
     */
    public function copy(int $id)
    {
        (new GoodsService())->copy($id);
        return success('SUCCESS');
    }

    /**
     * 服务项目删除
     * @description 服务项目删除
     * @param $id  服务项目id
     * @return \think\Response
     */
    public function del()
    {
        $data = $this->request->params([
            ['goods_ids', ''],
        ]);
        (new GoodsService())->del($data);
        return success('DELETE_SUCCESS');
    }


    /**
     * 服务上下架
     * @description 修改服务上下架状态
     */
    public function editStatus()
    {
        $data = $this->request->params([
            ['goods_ids', ''],
            ["status", 0],
        ]);
        (new GoodsService())->editStatus($data['goods_ids'], $data['status']);
        return success('SUCCESS');
    }

    /**
     * 获取列表不分页
     * @description 获取列表
     */
    public function getLists()
    {
        $data = $this->request->params([
            ["goods_name", ""],
            ["create_time", ""],
            ["category_id", ""]
        ]);
        return success((new GoodsService())->getLists($data));
    }

    /**
     * 查询商品SKU规格列表
     * @description 查询商品SKU规格列表
     * @return \think\Response
     */
    public function sku()
    {
        $data = $this->request->params([
            ['goods_id', '']
        ]);

        return success((new GoodsService())->getSkuList($data));
    }



    /**
     * 更新排序
     * @description 更新排序
     * @param int $id
     * @return \think\Response
     */
    public function editSort(int $id)
    {
        $data = $this->request->params([
            ["sort", 0],
        ]);
        (new GoodsService())->editSort($id, $data);
        return success('SUCCESS');
    }

    /**
     * 商品选择分页列表
     * @description 商品选择分页列表
     * @return \think\Response
     */
    public function select()
    {
        $data = $this->request->params([
            ['goods_name', ''], // 搜索关键词
            ["goods_category", ""], // 商品分类
            ['goods_ids', ''], // 已选商品id集合
            ['verify_goods_ids', ''], // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
            ['create_time', []],
            ['keyword', []],
        ]);
        return success((new GoodsService())->getSelectPage($data));
    }

    /**
     * 商品选择分页列表(带sku)
     * @return \think\Response
     */
    public function selectGoodsSku()
    {
        $data = $this->request->params([
            ['goods_name', ''], // 搜索关键词
            ["goods_category", ""], // 商品分类
            ['goods_ids', ''], // 已选商品id集合
            ['verify_goods_ids', ''], // 检测商品id集合是否存在，移除不存在的商品id，纠正数据准确性
        ]);

        return success((new GoodsService())->getSelectSku($data));
    }

    /**
     * 商品选择分页列表（代客下单专用）
     * @description 获取商品选择分页列表（代客下单专用）
     * @return \think\Response
     */
    public function buyGoodsSelect()
    {
        $data = $this->request->params([
            ['keyword', ''], // 搜索关键词
            ["goods_category", ""], // 商品分类
            ["member_id", 0], // 会员id
        ]);

        return success((new GoodsService())->getBuyGoodsSelect($data));
    }

    /**
     * 已选商品分页列表（代客下单专用）
     * @description 获取已选商品分页列表（代客下单专用）
     * @return \think\Response
     */
    public function buyGoodsSelected()
    {
        $data = $this->request->params([
            ['sku_ids', []],
            ['member_id', 0],
        ]);

        return success((new GoodsService())->getBuyGoodsSelected($data));
    }

    /**
     * 获取商品规格信息，切换规格（代客下单专用）
     * @description 获取商品规格信息，切换规格（代客下单专用）
     */
    public function buySkuSelect()
    {
        $data = $this->request->params([
            ['sku_id', 0],
            ['member_id', 0],
        ]);
        return success((new GoodsService())->getBuySkuSelect($data));
    }
}
