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

namespace addon\phone_shop\app\api\controller\goods;

use addon\phone_shop\app\service\api\goods\GoodsService;
use core\base\BaseApiController;

/**
 * 商品
 * Class Goods
 * @package addon\phone_shop\app\api\controller\goods
 */
class Goods extends BaseApiController
{

    /**
     * 获取商品列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            [ 'keyword', '' ], // 搜索关键词
            [ "goods_category", "" ], // 商品分类
            [ "brand_id", "" ], // 品牌id
            [ "label_ids", "" ], // 商品标签
            [ "start_price", "" ], // 价格开始区间
            [ "end_price", "" ], // 价格结束区间
            [ 'order', '' ], // 排序方式（综合：空，销量：sale_num，价格：price）
            [ 'sort', 'desc' ], // 升序：asc，降序：desc
            [ 'coupon_id', '' ], // 优惠券id
            [ 'sku_no' , ''],
            [ 'status' , ''],
            [ 'create_time','' ],
            [ 'memory_id','' ]

        ]);

        return success(( new GoodsService() )->getPage($data));
    }

    /**
     * 获取商品详情
     * @return \think\Response
     */
    public function detail()
    {
        $data = $this->request->params([
            [ 'goods_id', 0 ],
            [ 'sku_id', 0 ],
        ]);
        return success(( new GoodsService() )->getDetail($data));
    }

    /**
     * 获取商品规格信息，切换规格
     */
    public function sku()
    {
        $data = $this->request->params([
            [ 'sku_id', 0 ],
        ]);
        return success(( new GoodsService() )->getSku($data[ 'sku_id' ]));
    }

    /**
     * 获取商品列表供组件调用
     * @return \think\Response
     */
    public function components()
    {
        $data = $this->request->params([
            [ 'num', 0 ],
            [ 'goods_ids', '' ],
            [ 'goods_category', 0 ],
            [ 'order', '' ], // 排序方式（综合：空，销量：sale_num，价格：price）
        ]);

        return success(( new GoodsService() )->getGoodsComponents($data));
    }

    /**
     * operationGoods
     * 商品的上下架
     * */ 
    public function operationGoods()
    {

        $data = $this->request->params([
            [ 'goods_id', '' ],
        ]);

        return success(( new GoodsService() )->operationGoods($data));
    }
    
    
  // 添加商品
    public function add()
    {
        $data = $this->request->params([
            
                [ "goods_name", "" ],
                [ "sub_title", "" ],
                [ "goods_type", "real" ],
                [ "goods_image", "" ],
                [ "goods_category", '' ],
                [ "brand_id", 0 ],
                [ "label_ids", "" ],
                [ 'service_ids', '' ],
                [ 'supplier_id', 0 ],
                [ "status", 0 ],
                [ "sort", 0 ],
                [ 'attr_id', 0 ],
                [ 'attr_format', '' ],
                // 是否可以代理商品
                [ 'is_proxy', 0 ],
                [ 'memory_ids', '' ],
    
                // 规格类型，single：单规格，multi：多规格
                [ 'spec_type', 'single' ],
    
                // 单规格数据
                [ "price", 0 ],
                [ "market_price", 0 ],
                [ "cost_price", 0 ],
                [ "weight", 0 ],
                [ "volume", 0 ],
                [ "stock", 1 ],
                [ "sku_no", '' ],
                [ "unit", "件" ],
                [ "virtual_sale_num", 0 ],
    
                // 多规格数据
                // [ 'goods_spec_format', '' ],
                // [ 'goods_sku_data', '' ],
    
                // 配送设置
                [ "delivery_type", [ "express","store"] ],
                [ "is_free_shipping", 1],
                [ 'fee_type', '' ],
                [ 'delivery_money', 0 ],
                [ "delivery_template_id", 0 ],
    
                // 商品详情
                [ "goods_desc", "", false ],
                [ "goods_url", "", false ],
    
    
                [ 'member_discount', 'fixed_price' ], // 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
                [ 'poster_id', 0 ] // 海报id 

        ]);
        return success(( new GoodsService() )->add($data));
    }
    
    // getMemoryList
    public function getMemoryList()
    {
        // 分类id
        $data = $this->request->params([
            [ 'id', 6],
        ]);
        return success(( new GoodsService() )->getMemoryList($data));
    }
    // put
    public function put($goods_id )
    {
        $data = $this->request->params([
            [ "goods_name", "" ],
                [ "sub_title", "" ],
                [ "goods_type", "real" ],
                [ "goods_image", "" ],
                [ "goods_category", '' ],
                [ "brand_id", 0 ],
                [ "label_ids", "" ],
                [ 'service_ids', '' ],
                [ 'supplier_id', 0 ],
                [ "status", 0 ],
                [ "sort", 0 ],
                [ 'attr_id', 0 ],
                [ 'attr_format', '' ],
                // 是否可以代理商品
                [ 'is_proxy', 0 ],
                [ 'memory_ids', '' ],
    
                // 规格类型，single：单规格，multi：多规格
                // [ 'spec_type', 'single' ],
    
                // 单规格数据
                [ "price", 0 ],
                [ "market_price", 0 ],
                [ "cost_price", 0 ],
                [ "weight", 0 ],
                [ "volume", 0 ],
                [ "stock", 1 ],
                [ "sku_no", '' ],
                [ "unit", "件" ],
                [ "virtual_sale_num", 0 ],
    
                // 多规格数据
                // [ 'goods_spec_format', '' ],
                // [ 'goods_sku_data', '' ],
    
                // 配送设置
                [ "delivery_type", [ "express","store"] ],
                [ "is_free_shipping", 1],
                [ 'fee_type', '' ],
                [ 'delivery_money', 0 ],
                [ "delivery_template_id", 0 ],
    
                // 商品详情
                [ "goods_desc", "", false ],
                [ "goods_url", "", false ],
    
    
                [ 'member_discount', 'fixed_price' ], // 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
                [ 'poster_id', 0 ] // 海报id 

        ]);

   
        return success(( new GoodsService() )->update($goods_id, $data));
    }
}
