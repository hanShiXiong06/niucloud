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

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

/**
 * 商城系统
 */
Route::group('phone_shop', function() {

    /************************************************** 配送相关接口 *****************************************************/
    //物流公司 分页列表
    Route::get('delivery/company', 'addon\phone_shop\app\adminapi\controller\delivery\Company@pages');

    //物流公司 列表
    Route::get('delivery/company/list', 'addon\phone_shop\app\adminapi\controller\delivery\Company@lists');

    //物流公司 详情
    Route::get('delivery/company/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Company@info');

    //物流公司 添加
    Route::post('delivery/company', 'addon\phone_shop\app\adminapi\controller\delivery\Company@add');

    //物流公司 编辑
    Route::put('delivery/company/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Company@edit');

    //物流公司 删除
    Route::delete('delivery/company/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Company@del');

    //物流查询接口 设置
    Route::post('delivery/search', 'addon\phone_shop\app\adminapi\controller\delivery\DeliverySearch@setConfig');

    //物流跟踪接口 查询
    Route::get('delivery/search', 'addon\phone_shop\app\adminapi\controller\delivery\DeliverySearch@getConfig');

    //运费模版 分页列表
    Route::get('shipping/template', 'addon\phone_shop\app\adminapi\controller\delivery\ShippingTemplate@pages');

    //运费模版 列表
    Route::get('shipping/template/list', 'addon\phone_shop\app\adminapi\controller\delivery\ShippingTemplate@lists');

    //运费模版 详情
    Route::get('shipping/template/:template_id', 'addon\phone_shop\app\adminapi\controller\delivery\ShippingTemplate@info');

    //运费模版 添加
    Route::post('shipping/template', 'addon\phone_shop\app\adminapi\controller\delivery\ShippingTemplate@add');

    //运费模版 编辑
    Route::put('shipping/template/:template_id', 'addon\phone_shop\app\adminapi\controller\delivery\ShippingTemplate@edit');

    //运费模版 删除
    Route::delete('shipping/template/:template_id', 'addon\phone_shop\app\adminapi\controller\delivery\ShippingTemplate@del');

    //自提门店列表（分页）
    Route::get('delivery/store', 'addon\phone_shop\app\adminapi\controller\delivery\Store@lists');

    //自提门店列表（不分页）
    Route::get('delivery/store/list', 'addon\phone_shop\app\adminapi\controller\delivery\Store@getList');

    //自提门店详情
    Route::get('delivery/store/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Store@info');

    //添加自提门店
    Route::post('delivery/store', 'addon\phone_shop\app\adminapi\controller\delivery\Store@add');

    //编辑自提门店
    Route::put('delivery/store/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Store@edit');

    //删除自提门店
    Route::delete('delivery/store/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Store@del');

    //物流配置
    Route::get('delivery/deliveryList', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@getDeliveryList');
    Route::put('delivery/setConfig', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@setDeliveryConfig');
    Route::put('delivery/deliverySort', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@setDeliverySort');

    //配送员列表
    Route::get('delivery/staff', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@lists');

    //配送员详情
    Route::get('delivery/staff/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@info');

    //添加配送员
    Route::post('delivery/staff', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@add');

    //编辑配送员
    Route::put('delivery/staff/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@edit');

    //删除配送员
    Route::delete('delivery/staff/:id', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@del');

    // 获取同城配送设置
    Route::get('local', 'addon\phone_shop\app\adminapi\controller\delivery\Local@getLocal');

    // 设置同城配送
    Route::put('local', 'addon\phone_shop\app\adminapi\controller\delivery\Local@setLocal');

    /************************************************** 接口管理 *******************************************************/

    // 电子面单 分页列表
    Route::get('electronic_sheet', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@pages');

    // 电子面单 列表
    Route::get('electronic_sheet/list', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@lists');

    // 电子面单 详情
    Route::get('electronic_sheet/:id', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@info');

    // 电子面单 添加
    Route::post('electronic_sheet', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@add');

    // 电子面单 编辑
    Route::put('electronic_sheet/:id', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@edit');

    // 电子面单 删除
    Route::delete('electronic_sheet/:id', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@del');

    // 电子面单 设为默认模板
    Route::put('electronic_sheet/setDefault/:id', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@setDefault');

    // 电子面单 获取设置
    Route::get('electronic_sheet/config', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@getConfig');

    // 电子面单 设置
    Route::post('electronic_sheet/config', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@setConfig');

    // 电子面单 获取邮费支付方式类型
    Route::get('electronic_sheet/paytype', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@getPayType');

    // 电子面单 打印
    Route::post('electronic_sheet/print', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@printElectronicSheet');

    //商品分页列表
    Route::get('goods', 'addon\phone_shop\app\adminapi\controller\goods\Goods@pages');

    //商品详情
    Route::get('goods/:id', 'addon\phone_shop\app\adminapi\controller\goods\Goods@info');

    //添加实物商品
    Route::post('goods', 'addon\phone_shop\app\adminapi\controller\goods\Goods@add');

    //编辑实物商品
    Route::put('goods/:id', 'addon\phone_shop\app\adminapi\controller\goods\Goods@edit');

    // 商品添加/编辑数据
    Route::get('goods/init', 'addon\phone_shop\app\adminapi\controller\goods\Goods@init');

    //添加虚拟商品
    Route::post('goods/virtual', 'addon\phone_shop\app\adminapi\controller\goods\VirtualGoods@add');

    //编辑虚拟商品
    Route::put('goods/virtual/:id', 'addon\phone_shop\app\adminapi\controller\goods\VirtualGoods@edit');

    // 商品添加/编辑数据
    Route::get('goods/virtual/init', 'addon\phone_shop\app\adminapi\controller\goods\VirtualGoods@init');

    //删除商品
    Route::put('goods/delete', 'addon\phone_shop\app\adminapi\controller\goods\Goods@del');

    // 回收站商品分页列表
    Route::get('goods/recycle', 'addon\phone_shop\app\adminapi\controller\goods\Goods@recyclePages');

    //商品恢复
    Route::put('goods/recycle', 'addon\phone_shop\app\adminapi\controller\goods\Goods@recycle');

    // 修改商品排序号
    Route::put('goods/sort', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editSort');

    // 修改商品上下架状态
    Route::put('goods/status', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editStatus');

    // 复制商品
    Route::put('goods/copy/:goods_id', 'addon\phone_shop\app\adminapi\controller\goods\Goods@copy');

    // 获取商品选择分页列表
    Route::get('goods/select', 'addon\phone_shop\app\adminapi\controller\goods\Goods@select');

    // 获取商品选择分页列表带sku
    Route::get('goods/selectgoodssku', 'addon\phone_shop\app\adminapi\controller\goods\Goods@selectgoodssku');

    // 获取商品SKU规格列表
    Route::get('goods/sku', 'addon\phone_shop\app\adminapi\controller\goods\Goods@sku');

    // 编辑商品规格列表库存
    Route::put('goods/sku/stock', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editGoodsListStock');

    // 编辑商品规格列表价格
    Route::put('goods/sku/price', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editGoodsListPrice');

    // 编辑商品规格列表会员价格
    Route::put('goods/sku/member_price', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editGoodsListMemberPrice');

    // 获取商品SKU规格列表
    Route::get('goods/active/count', 'addon\phone_shop\app\adminapi\controller\goods\Goods@getActiveGoodsCount');

    // 获取商品类型
    Route::get('goods/type', 'addon\phone_shop\app\adminapi\controller\goods\Goods@type');

    //商品标签分页列表
    Route::get('goods/label', 'addon\phone_shop\app\adminapi\controller\goods\Label@pages');

    //商品标签列表
    Route::get('goods/label/list', 'addon\phone_shop\app\adminapi\controller\goods\Label@lists');

    //商品标签详情
    Route::get('goods/label/:id', 'addon\phone_shop\app\adminapi\controller\goods\Label@info');

    //添加商品标签
    Route::post('goods/label', 'addon\phone_shop\app\adminapi\controller\goods\Label@add');

    //编辑商品标签
    Route::put('goods/label/:id', 'addon\phone_shop\app\adminapi\controller\goods\Label@edit');

    //删除商品标签
    Route::delete('goods/label/:id', 'addon\phone_shop\app\adminapi\controller\goods\Label@del');

    // 修改商品标签排序号
    Route::put('goods/label/sort', 'addon\phone_shop\app\adminapi\controller\goods\Label@modifySort');

    // 修改商品标签排序号
    Route::put('goods/label/status', 'addon\phone_shop\app\adminapi\controller\goods\Label@modifyStatus');

    //商品标签分组分页列表
    Route::get('goods/label/group', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@pages');

    //商品标签分组列表
    Route::get('goods/label/group/list', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@lists');

    //商品标签分组详情
    Route::get('goods/label/group/:id', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@info');

    //添加商品标签分组
    Route::post('goods/label/group', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@add');

    //编辑商品标签分组
    Route::put('goods/label/group/:id', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@edit');

    //删除商品标签分组
    Route::delete('goods/label/group/:id', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@del');

    // 修改商品标签分组排序号
    Route::put('goods/label/group/sort', 'addon\phone_shop\app\adminapi\controller\goods\LabelGroup@modifySort');
      

    //商品品牌分页列表
    Route::get('goods/brand', 'addon\phone_shop\app\adminapi\controller\goods\Brand@pages');

    //商品品牌列表
    Route::get('goods/brand/list', 'addon\phone_shop\app\adminapi\controller\goods\Brand@lists');

    //商品品牌详情
    Route::get('goods/brand/:id', 'addon\phone_shop\app\adminapi\controller\goods\Brand@info');

    //添加商品品牌
    Route::post('goods/brand', 'addon\phone_shop\app\adminapi\controller\goods\Brand@add');

    //编辑商品品牌
    Route::put('goods/brand/:id', 'addon\phone_shop\app\adminapi\controller\goods\Brand@edit');

    //删除商品品牌
    Route::delete('goods/brand/:id', 'addon\phone_shop\app\adminapi\controller\goods\Brand@del');

    // 修改商品品牌排序号
    Route::put('goods/brand/sort', 'addon\phone_shop\app\adminapi\controller\goods\Brand@modifySort');

    //商品服务分页列表
    Route::get('goods/service', 'addon\phone_shop\app\adminapi\controller\goods\Service@pages');

    //商品服务列表
    Route::get('goods/service/list', 'addon\phone_shop\app\adminapi\controller\goods\Service@lists');

    //商品服务详情
    Route::get('goods/service/:id', 'addon\phone_shop\app\adminapi\controller\goods\Service@info');

    //添加商品服务
    Route::post('goods/service', 'addon\phone_shop\app\adminapi\controller\goods\Service@add');

    //编辑商品服务
    Route::put('goods/service/:id', 'addon\phone_shop\app\adminapi\controller\goods\Service@edit');

    //删除商品服务
    Route::delete('goods/service/:id', 'addon\phone_shop\app\adminapi\controller\goods\Service@del');

    //商品分类列表树结构
    Route::get('goods/tree', 'addon\phone_shop\app\adminapi\controller\goods\Category@tree');

    Route::get('goods/category', 'addon\phone_shop\app\adminapi\controller\goods\Category@lists');

    //商品分类详情
    Route::get('goods/category/:id', 'addon\phone_shop\app\adminapi\controller\goods\Category@info');

    //添加商品分类
    Route::post('goods/category', 'addon\phone_shop\app\adminapi\controller\goods\Category@add');

    //编辑商品分类
    Route::put('goods/category/:id', 'addon\phone_shop\app\adminapi\controller\goods\Category@edit');

    //删除商品分类
    Route::delete('goods/category/:id', 'addon\phone_shop\app\adminapi\controller\goods\Category@del');

    //编辑商品分类
    Route::post('goods/category/update', 'addon\phone_shop\app\adminapi\controller\goods\Category@editCategory');

    // 获取商品分类配置
    Route::post('goods/category/config', 'addon\phone_shop\app\adminapi\controller\goods\Category@setGoodsCategoryConfig');

    // 获取商品分类配置
    Route::get('goods/category/config', 'addon\phone_shop\app\adminapi\controller\goods\Category@getGoodsCategoryConfig');

    // 获取商品分类树结构供弹框调用
    Route::get('goods/category/components', 'addon\phone_shop\app\adminapi\controller\goods\Category@components');

    // 商品参数分页列表
    Route::get('goods/attr', 'addon\phone_shop\app\adminapi\controller\goods\Attr@pages');

    // 商品参数列表
    Route::get('goods/attr/list', 'addon\phone_shop\app\adminapi\controller\goods\Attr@lists');

    // 商品参数详情
    Route::get('goods/attr/:id', 'addon\phone_shop\app\adminapi\controller\goods\Attr@info');

    // 添加商品参数
    Route::post('goods/attr', 'addon\phone_shop\app\adminapi\controller\goods\Attr@add');

    // 编辑商品参数
    Route::put('goods/attr/:id', 'addon\phone_shop\app\adminapi\controller\goods\Attr@edit');

    // 删除商品参数
    Route::delete('goods/attr/:id', 'addon\phone_shop\app\adminapi\controller\goods\Attr@del');

    // 修改商品参数排序号
    Route::put('goods/attr/sort', 'addon\phone_shop\app\adminapi\controller\goods\Attr@modifySort');

    // 修改商品参数名称
    Route::put('goods/attr/attr_name', 'addon\phone_shop\app\adminapi\controller\goods\Attr@modifyAttrName');

    // 修改商品参数值
    Route::put('goods/attr/attr_value', 'addon\phone_shop\app\adminapi\controller\goods\Attr@modifyAttrValueFormat');

    
        Route::get('goods/memory_group', 'addon\phone_shop\app\adminapi\controller\goods\MemoryGroup@lists');                    // 获取列表
        Route::get('goods/memory_group/:id', 'addon\phone_shop\app\adminapi\controller\goods\MemoryGroup@detail');               // 获取详情
        Route::post('goods/memory_group', 'addon\phone_shop\app\adminapi\controller\goods\MemoryGroup@add');                    // 添加
        Route::put('goods/memory_group/:id', 'addon\phone_shop\app\adminapi\controller\goods\MemoryGroup@edit');                 // 编辑
        Route::delete('goods/memory_group/:id', 'addon\phone_shop\app\adminapi\controller\goods\MemoryGroup@delete');            // 删除
        Route::get('goods/memory', 'addon\phone_shop\app\adminapi\controller\goods\Memory@list');                        // 获取列表
        Route::get('goods/memorys', 'addon\phone_shop\app\adminapi\controller\goods\Memory@lists');                        // 获取列表
        Route::get('goods/memory/:id', 'addon\phone_shop\app\adminapi\controller\goods\Memory@detail');                    // 获取详情
        Route::post('goods/memory', 'addon\phone_shop\app\adminapi\controller\goods\Memory@add');                         // 添加
        Route::put('goods/memory/:id', 'addon\phone_shop\app\adminapi\controller\goods\Memory@edit');                      // 编辑
        Route::delete('goods/memory/:id', 'addon\phone_shop\app\adminapi\controller\goods\Memory@delete');                 // 删除
        Route::get('goods/memory_group/:group_id', 'addon\phone_shop\app\adminapi\controller\goods\Memory@getListByGroupId');  // 根据分组获取列表
    
    



    /************************************************** 订单相关接口 *****************************************************/
    //交易配置
    Route::post('order/config', 'addon\phone_shop\app\adminapi\controller\order\Config@setConfig');
    Route::get('order/config', 'addon\phone_shop\app\adminapi\controller\order\Config@getConfig');

    //订单列表
    Route::get('order/list', 'addon\phone_shop\app\adminapi\controller\order\Order@lists');

    //订单详情
    Route::get('order/detail/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@detail');

    //获取 订单类型
    Route::get('order/type', 'addon\phone_shop\app\adminapi\controller\order\Order@getOrderType');

    //获取 订单状态
    Route::get('order/status', 'addon\phone_shop\app\adminapi\controller\order\Order@getOrderStatus');

    //订单关闭
    Route::put('order/close/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@orderClose');

    //订单改价
    Route::put('order/edit_price', 'addon\phone_shop\app\adminapi\controller\order\Order@editPrice');

    //订单配送修改
    Route::put('order/edit_delivery', 'addon\phone_shop\app\adminapi\controller\order\Order@editDelivery');

    //订单配送修改信息
    Route::get('order/edit_delivery', 'addon\phone_shop\app\adminapi\controller\order\Order@editDeliveryData');

    //订单发货
    Route::put('order/delivery', 'addon\phone_shop\app\adminapi\controller\order\Order@orderDelivery');

    //订单项发货
    Route::put('order/goods/delivery/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@orderDelivery');

    //获取订单配送方式
    Route::get('order/delivery_type', 'addon\phone_shop\app\adminapi\controller\order\Order@getDeliveryType');

    //商家留言
    Route::put('order/shop_remark', 'addon\phone_shop\app\adminapi\controller\order\Order@setShopRemark');

    //订单完成
    Route::put('order/finish/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@orderFinish');

    //获取 物流包裹信息（跟踪信息）
    Route::get('order/delivery/package', 'addon\phone_shop\app\adminapi\controller\order\Order@getOrderPackage');

    //获取 物流包裹列表
    Route::get('order/delivery/package/list', 'addon\phone_shop\app\adminapi\controller\order\Order@getDeliveryPackageList');

    //获取 支付类型
    Route::get('order/pay/type', 'addon\phone_shop\app\adminapi\controller\order\Order@getPayType');

    //获取 订单来源
    Route::get('order/from', 'addon\phone_shop\app\adminapi\controller\order\Order@getOrderFrom');

    //订单维权 列表
    Route::get('order/refund', 'addon\phone_shop\app\adminapi\controller\refund\Refund@lists');

    //订单维权 详情
    Route::get('order/refund/:id', 'addon\phone_shop\app\adminapi\controller\refund\Refund@detail');

    //订单维权审核
    Route::put('order/refund/audit/:order_refund_no', 'addon\phone_shop\app\adminapi\controller\refund\Refund@auditApply');

    //订单维权审核
    Route::put('order/refund/delivery/:order_refund_no', 'addon\phone_shop\app\adminapi\controller\refund\Refund@auditRefundGoods');

    //订单维权 可退款金额
    Route::get('order/refund/refund_money', 'addon\phone_shop\app\adminapi\controller\refund\Refund@getOrderRefundMoney');

    //订单维权 商家主动退款
    Route::post('order/refund/active', 'addon\phone_shop\app\adminapi\controller\refund\Refund@shopActiveRefund');
    /************************************************** 订单发货批量操作相关接口 *****************************************************/

    //订单批量操作 列表
    Route::get('order_batch_delivery', 'addon\phone_shop\app\adminapi\controller\order\Order@getOrderBatchDeliveryPage');

    //订单批量操作 详情
    Route::get('order_batch_delivery/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@getOrderBatchDeliveryInfo');

    //批量发货
    Route::put('order_batch_delivery/add_batch_order_delivery', 'addon\phone_shop\app\adminapi\controller\order\Order@addBatchOrderDelivery');

    //订单批量操作类型
    Route::get('order_batch_delivery/get_type', 'addon\phone_shop\app\adminapi\controller\order\Order@getBatchType');

    //订单批量操作状态
    Route::get('order_batch_delivery/get_status', 'addon\phone_shop\app\adminapi\controller\order\Order@getBatchStatus');

    //营销中心
    Route::get('marketing', 'addon\phone_shop\app\adminapi\controller\marketing\Index@index');

    /************************************************** 优惠券相关接口 *****************************************************/
    //优惠券列表
    Route::get('goods/coupon', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@lists');

    //优惠券初始化信息
    Route::get('goods/coupon/init', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@init');

    //添加优惠券
    Route::post('goods/coupon', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@add');

    //优惠券领取记录
    Route::get('goods/coupon/records', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@getMemberCoupon');

    //优惠券详情
    Route::get('goods/coupon/detail/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@info');

    //编辑优惠券
    Route::put('goods/coupon/edit/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@edit');

    //优惠券设置状态
    Route::put('goods/coupon/setstatus/:status', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@setCouponStatus');

    //优惠券失效
    Route::put('goods/coupon/invalid/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@couponInvalid');

    //删除优惠券
    Route::delete('goods/coupon/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@del');

    // 查询选中的优惠券
    Route::get('goods/coupon/selected', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@getSelectedLists');

    //商家地址库列表
    Route::get('shop_address', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@lists');

    //商家地址库详情
    Route::get('shop_address/:id', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@info');

    //添加商家地址库
    Route::post('shop_address', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@add');

    //编辑商家地址库
    Route::put('shop_address/:id', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@edit');

    //删除商家地址库
    Route::delete('shop_address/:id', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@del');

    // 默认发货地址
    Route::get('shop_address/default/delivery', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@defaultDelivery');

    //获取商家收货地址库
    Route::get('order/refund/address', 'addon\phone_shop\app\adminapi\controller\shop_address\ShopAddress@getList');

    //商品评价 列表
    Route::get('goods/evaluate', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@lists');

    //商品评价 添加
    Route::post('goods/evaluate', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@add');

    //商品评价 删除
    Route::delete('goods/evaluate/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@del');

    //商品评价 回复
    Route::put('goods/evaluate/reply/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@evaluateReply');

    //商品评价 通过
    Route::put('goods/evaluate/adopt/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@adopt');

    //商品评价 拒绝
    Route::put('goods/evaluate/refuse/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@refuse');

    //商品评价 置顶
    Route::put('goods/evaluate/topping/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@topping');

    //商品评价 取消置顶
    Route::put('goods/evaluate/cancel_topping/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@cancelTopping');

    Route::get('stat/total', 'addon\phone_shop\app\adminapi\controller\Stat@total');
    Route::get('stat/today', 'addon\phone_shop\app\adminapi\controller\Stat@today');
    Route::get('stat/yesterday', 'addon\phone_shop\app\adminapi\controller\Stat@yesterday');
    Route::get('stat', 'addon\phone_shop\app\adminapi\controller\Stat@stat');
    Route::get('stat/order', 'addon\phone_shop\app\adminapi\controller\Stat@order');
    Route::get('stat/goods', 'addon\phone_shop\app\adminapi\controller\Stat@goods');

    // 发票列表
    Route::get('invoice', 'addon\phone_shop\app\adminapi\controller\order\Invoice@lists');

    // 发票信息
    Route::get('invoice/:id', 'addon\phone_shop\app\adminapi\controller\order\Invoice@info');

    // 开票
    Route::put('invoice/:id', 'addon\phone_shop\app\adminapi\controller\order\Invoice@invoicing');

    /************************************************** 限时折扣 *****************************************************/
    //限时折扣列表
    Route::get('active/discount', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@lists');

    //添加
    Route::post('active/discount', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@add');

    //编辑
    Route::put('active/discount/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@edit');

    //删除
    Route::delete('active/discount/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@del');

    //关闭
    Route::put('active/discount/close/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@close');

    //详情
    Route::get('active/discount/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@detail');

    //状态
    Route::get('active/status', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@status');

    //参与订单
    Route::get('active/discount/order/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@order');

    //参与会员
    Route::get('active/discount/member/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@member');

    //参与商品
    Route::get('active/discount/goods/:active_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@goods');

    //获取配置
    Route::get('active/discount/config', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@banner');

    //设置配置
    Route::put('active/discount/config', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@setBanner');


    /************************************************** 积分商城 *****************************************************/
    //积分商城列表
    Route::get('active/exchange', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@lists');

    //商品类型
    Route::get('active/exchange/type', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@type');

    //商品类型
    Route::get('active/exchange/status', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@status');

    //添加积分商城
    Route::post('active/exchange', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@add');

    //积分商城详情
    Route::get('active/exchange/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@detail');

    //编辑积分商城
    Route::put('active/exchange/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@edit');

    //修改积分商城上下架状态
    Route::put('active/exchange/status/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@editStatus');

    //删除
    Route::delete('active/exchange/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@del');

    //修改排序号
    Route::put('active/exchange/sort/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@modifySort');

      // 一键同步商品 
      Route::get('goods/sync_goods_list', 'addon\phone_shop\app\adminapi\controller\goods\Goods@syncGoodsList');

      

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);








// USER_CODE_BEGIN -- phone_shop_site

Route::group('phone_shop', function () {

    //站点(二手)管理列表
    Route::get('site', 'addon\phone_shop\app\adminapi\controller\site\Site@lists');
    //站点(二手)管理详情
    Route::get('site/:id', 'addon\phone_shop\app\adminapi\controller\site\Site@info');
    //添加站点(二手)管理
    Route::post('site', 'addon\phone_shop\app\adminapi\controller\site\Site@add');
    //编辑站点(二手)管理
    Route::put('site/:id', 'addon\phone_shop\app\adminapi\controller\site\Site@edit');
    //删除站点(二手)管理
    Route::delete('site/:id', 'addon\phone_shop\app\adminapi\controller\site\Site@del');
     //-----------------------------------------------------------------------------------------
    // 回收商相关的CURD
    // 添加回收商
    Route::post('recycler', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecycler@add');
    // 查询回收商信息
    Route::get('recycler', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecycler@info');
    // 更新回收商信息
    Route::put('recycler/:id', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecycler@update');
    // 删除回收商
    Route::delete('recycler/:id', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecycler@delete');
    // role
    Route::get('recycler/role' ,'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecycler@role' );
    //-----------------------------------------------------------------------------------------
    // 回收商价格配置
    // 添加回收商价格配置
    Route::post('recycler/price/config', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@add');
    // 查询回收商价格配置
    // Route::get('recycler/price/config', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@info');
    // 更新回收商价格配置
    Route::put('recycler/price/config/:id', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@update');
    // 删除回收商价格配置
    Route::delete('recycler/price/config/:id', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@delete');

    // phone_shop/recycler/price/config/1 获取回收商价格配置
    Route::get('recycler/price/config/:id', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@info');
    // phone_shop/recycler/price/config 设置回收商价格配置
    Route::put('recycler/price/config', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@price_update');
    // phone_shop/recycler/price/config/1 删除回收商价格配置
    Route::delete('recycler/price/config/:id', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@price_delete');
    // 根据site_id 获取回收商价格配置
    Route::get('recycler/price/config/site', 'addon\phone_shop\app\adminapi\controller\site\PhoneShopRecyclerPriceConfig@getRecyclerPriceConfigBySiteId');

     //-----------------------------------------------------------------------------------------
    // phone_shop_recycler_price_range
    // 回收商价格区间


    // 站点代理相关路由
        Route::group('site_agent', function () {
            Route::get('lists', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@lists');
            Route::get('available', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@getAvailableSites');
            Route::post('add', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@add');
            Route::put('modify_status', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@modifyStatus');
            Route::delete('del/:id', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@del');
        });
  
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- phone_shop_site



