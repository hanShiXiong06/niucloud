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
Route::group('phone_shop', function () {
    Route::get('goods/tier_pricing', 'addon\phone_shop\app\adminapi\controller\goods\TierPricing@info');
    Route::post('goods/tier_pricing', 'addon\phone_shop\app\adminapi\controller\goods\TierPricing@save');
    Route::get('goods/tier_pricing/preview', 'addon\phone_shop\app\adminapi\controller\goods\TierPricing@preview');

    /************************************************** 同行商品转发权益审核 *****************************************************/
    Route::get('forward/application', 'addon\phone_shop\app\adminapi\controller\member\ForwardApplication@pages');
    Route::get('forward/application/:id', 'addon\phone_shop\app\adminapi\controller\member\ForwardApplication@info');
    Route::put('forward/application/:id/review', 'addon\phone_shop\app\adminapi\controller\member\ForwardApplication@review');

    /************************************************** 代下单收银台(点菜式开单) *****************************************************/
    Route::get('cashier/goods', 'addon\phone_shop\app\adminapi\controller\cashier\Cashier@goods');
    Route::get('cashier/category_tree', 'addon\phone_shop\app\adminapi\controller\cashier\Cashier@categoryTree');
    Route::post('cashier/checkout', 'addon\phone_shop\app\adminapi\controller\cashier\Cashier@checkout');

    /************************************************** 待上架货源(中台定价设备) *****************************************************/
    Route::get('device_intake', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@pages');
    Route::get('device_intake/seed_test', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@seedTest'); // 临时造数,上线前删
    Route::get('device_intake/sync_schema', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@syncSchema'); // 手动跑表结构迁移
    Route::get('device_intake/pending_count', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@pendingCount');
    Route::get('device_intake/material_policy', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@materialPolicy');
    Route::get('device_intake/material/:id', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@materialInfo');
    Route::put('device_intake/material/:id', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@saveMaterial');
    // 上架映射配置(扩展口) —— 须在 :id 之前,避免被通配吞掉
    Route::get('device_intake/mapping_config', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@mappingConfig');
    Route::post('device_intake/mapping_config', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@saveMappingConfig');
    Route::post('device_intake/preview', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@preview'); // 建品预览(预填6字段)
    Route::post('device_intake/build', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@build');
    Route::get('device_intake/:id', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@info');
    Route::put('device_intake/status', 'addon\phone_shop\app\adminapi\controller\intake\DeviceIntake@setStatus');

    /************************************************** 站点代理订阅关系(多站铺货/联动) *****************************************************/
    Route::get('agent/master_config', 'addon\phone_shop\app\adminapi\controller\agent\Agent@masterConfig');
    Route::put('agent/display_config', 'addon\phone_shop\app\adminapi\controller\agent\Agent@setDisplayConfig');
    Route::post('agent/master_config', 'addon\phone_shop\app\adminapi\controller\agent\Agent@setMasterConfig');
    Route::post('agent/sync_goods', 'addon\phone_shop\app\adminapi\controller\agent\Agent@syncGoods');
    Route::post('agent/sync_goods/step', 'addon\phone_shop\app\adminapi\controller\agent\Agent@syncGoodsStep');
    Route::get('agent/dashboard', 'addon\phone_shop\app\adminapi\controller\agent\Agent@dashboard');
    Route::get('agent/reference_sync', 'addon\phone_shop\app\adminapi\controller\agent\Agent@referenceSyncInfo');
    Route::post('agent/reference_sync', 'addon\phone_shop\app\adminapi\controller\agent\Agent@startReferenceSync');
    Route::post('agent/reference_sync/step', 'addon\phone_shop\app\adminapi\controller\agent\Agent@referenceSyncStep');
    // 分类映射静态路由必须放在 agent/:id 前，避免被通配路由吞掉。
    Route::get('agent/category_mapping', 'addon\phone_shop\app\adminapi\controller\agent\Agent@categoryMappingPages');
    Route::get('agent/category_mapping/summary', 'addon\phone_shop\app\adminapi\controller\agent\Agent@categoryMappingSummary');
    Route::get('agent/category_mapping/options', 'addon\phone_shop\app\adminapi\controller\agent\Agent@categoryMappingOptions');
    Route::post('agent/category_mapping/scan', 'addon\phone_shop\app\adminapi\controller\agent\Agent@scanCategoryMappings');
    Route::put('agent/category_mapping/:id/map', 'addon\phone_shop\app\adminapi\controller\agent\Agent@mapCategory');
    Route::post('agent/category_mapping/:id/create', 'addon\phone_shop\app\adminapi\controller\agent\Agent@createCategoryMapping');
    Route::put('agent/category_mapping/:id/ignore', 'addon\phone_shop\app\adminapi\controller\agent\Agent@ignoreCategoryMapping');
    Route::get('agent', 'addon\phone_shop\app\adminapi\controller\agent\Agent@pages');
    Route::post('agent', 'addon\phone_shop\app\adminapi\controller\agent\Agent@add');
    Route::put('agent/:id', 'addon\phone_shop\app\adminapi\controller\agent\Agent@edit');
    Route::delete('agent/:id', 'addon\phone_shop\app\adminapi\controller\agent\Agent@del');

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

    /************************************************** 提货点 *****************************************************/
    //提货点列表（分页）
    Route::get('delivery/store', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@lists');

    //提货点初始化数据
    Route::get('delivery/store/init', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@init');

    //提货点列表（不分页）
    Route::get('delivery/store/list', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@getList');

    //提货点详情
    Route::get('delivery/store/:id', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@info');

    //添加提货点
    Route::post('delivery/store', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@add');

    //编辑提货点
    Route::put('delivery/store/:id', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@edit');

    //删除提货点
    Route::delete('delivery/store/:id', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@del');

    //提货点提货类型
    Route::get('delivery/store/pick_up_type', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@getPickUpType');

    //修改提货点状态
    Route::put('delivery/store/modify_status', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryStore@modifyStatus');

    /************************************************** 配送服务商 *****************************************************/
    //获取配送服务商列表
    Route::get('delivery_store/delivery_service_list/:id', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryService@getDeliveryServiceList');

    //配送门店开通配送服务
    Route::put('delivery_store/delivery_shop_open/:id', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryService@deliveryServiceOpen');

    //配送门店品类修改
    Route::put('delivery_store/delivery_shop_edit/:id', 'addon\phone_shop\app\adminapi\controller\delivery_store\DeliveryService@deliveryServiceEdit');

    /************************************************** 配送设置 *****************************************************/
    //物流配置
    Route::get('delivery/deliveryList', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@getDeliveryList');
    Route::put('delivery/setConfig', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@setDeliveryConfig');

    //获取配送方式
    Route::get('delivery/delivery_type', 'addon\phone_shop\app\adminapi\controller\delivery\Delivery@getDeliveryType');

    /************************************************** 同城配送 *******************************************************/

    // 获取同城配送设置
    Route::get('local_delivery/config', 'addon\phone_shop\app\adminapi\controller\local_delivery\config\Local@getLocal');

    // 设置同城配送
    Route::put('local_delivery/config', 'addon\phone_shop\app\adminapi\controller\local_delivery\config\Local@setLocal');

    // 获取同城配送基础配置
    Route::get('local_delivery/base_config', 'addon\phone_shop\app\adminapi\controller\local_delivery\config\Config@getConfig');

    // 设置同城配送基础配置
    Route::put('local_delivery/base_config', 'addon\phone_shop\app\adminapi\controller\local_delivery\config\Config@setConfig');

    //获取已启用配送服务商列表
    Route::get('local_delivery/list', 'addon\phone_shop\app\adminapi\controller\local_delivery\service\LocalDeliveryService@getInUseList');

    //同城配送服务商列表
    Route::get('local_delivery/service', 'addon\phone_shop\app\adminapi\controller\local_delivery\service\LocalDeliveryService@lists');

    //同城配送服务商配置
    Route::get('local_delivery/service/:delivery_type', 'addon\phone_shop\app\adminapi\controller\local_delivery\service\LocalDeliveryService@getConfig');

    //同城配送服务商配置修改
    Route::put('local_delivery/service/:delivery_type', 'addon\phone_shop\app\adminapi\controller\local_delivery\service\LocalDeliveryService@setConfig');

    // 同城配送订单
    Route::get('local_delivery/order', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@pages');

    // 同城配送订单详情
    Route::get('local_delivery/order/:id', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@info');

    // 同城配送订单同步
    Route::put('local_delivery/order/sync/:id', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@syncOrder');

    // 同城配送订单取消原因
    Route::get('local_delivery/order/cancel_reason', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@getCancelReasonList');

    // 同城配送订单取消
    Route::put('local_delivery/order/cancel/:id', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@closeOrder');

    // 同城配送订单完成
    Route::put('local_delivery/order/finish/:id', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@finishOrder');

    // 同城配送订单状态列表
    Route::get('local_delivery/order/status', 'addon\phone_shop\app\adminapi\controller\local_delivery\order\LocalDeliveryOrder@getOrderStatusList');

    /************************************************** 商家配送 *******************************************************/

    //配送员分页列表
    Route::get('shop_delivery/staff', 'addon\phone_shop\app\adminapi\controller\shop_delivery\deliver\Deliver@pages');

    //配送员列表
    Route::get('shop_delivery/staff/list', 'addon\phone_shop\app\adminapi\controller\shop_delivery\deliver\Deliver@lists');

    //配送员详情
    Route::get('shop_delivery/staff/:id', 'addon\phone_shop\app\adminapi\controller\shop_delivery\deliver\Deliver@info');

    //添加配送员
    Route::post('shop_delivery/staff', 'addon\phone_shop\app\adminapi\controller\shop_delivery\deliver\Deliver@add');

    //编辑配送员
    Route::put('shop_delivery/staff/:id', 'addon\phone_shop\app\adminapi\controller\shop_delivery\deliver\Deliver@edit');

    //删除配送员
    Route::delete('shop_delivery/staff/:id', 'addon\phone_shop\app\adminapi\controller\shop_delivery\deliver\Deliver@del');

    // 商家配送订单
    Route::get('shop_delivery/order', 'addon\phone_shop\app\adminapi\controller\shop_delivery\order\ShopDeliveryOrder@pages');

    // 商家配送订单详情
    Route::get('shop_delivery/order/:id', 'addon\phone_shop\app\adminapi\controller\shop_delivery\order\ShopDeliveryOrder@info');

    // 商家配送订单取消
    Route::put('shop_delivery/order/cancel/:id', 'addon\phone_shop\app\adminapi\controller\shop_delivery\order\ShopDeliveryOrder@closeOrder');

    // 商家配送订单完成
    Route::put('shop_delivery/order/finish/:id', 'addon\phone_shop\app\adminapi\controller\shop_delivery\order\ShopDeliveryOrder@finishOrder');

    // 商家配送订单状态列表
    Route::get('shop_delivery/order/status', 'addon\phone_shop\app\adminapi\controller\shop_delivery\order\ShopDeliveryOrder@getOrderStatusList');

//    Route::get('third/init', 'addon\phone_shop\app\adminapi\controller\delivery\Local@getThirdPartyInit');

    /************************************************** 接口管理 *******************************************************/

    // 电子面单 分页列表
    Route::get('electronic_sheet', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@pages');

    // 电子面单 列表
    Route::get('electronic_sheet/list', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@lists');

    // 外部服务商面单任务（不改变发货状态）
    Route::post('electronic_sheet/provider_task', 'addon\phone_shop\app\adminapi\controller\delivery\ElectronicSheet@providerTask');

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

    // 商品 Excel 异步导入导出（静态路由必须位于 goods/:id 之前）
    Route::get('goods/transfer/template', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@template');
    Route::post('goods/transfer/import', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@import');
    Route::post('goods/transfer/export', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@export');
    Route::get('goods/transfer/tasks', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@tasks');
    Route::get('goods/transfer/tasks/:id', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@task');
    Route::post('goods/transfer/tasks/:id/retry', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@retry');
    Route::get('goods/transfer/tasks/:id/download', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@download');
    Route::get('goods/transfer/tasks/:id/notice', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@noticePreview');
    Route::post('goods/transfer/tasks/:id/notice', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@notice');
    Route::post('goods/transfer/notices/:id/retry', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@noticeRetry');
    Route::get('goods/transfer/notices/:id/results', 'addon\phone_shop\app\adminapi\controller\goods\GoodsTransfer@noticeResults');

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

    // 修改商品上下架状态（单商品）
    Route::put('goods/single/status', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editSingleStatus');

    // 复制商品
    Route::put('goods/copy/:goods_id', 'addon\phone_shop\app\adminapi\controller\goods\Goods@copy');

    // 获取商品选择分页列表
    Route::get('goods/select', 'addon\phone_shop\app\adminapi\controller\goods\Goods@select');

    // 获取商品选择分页列表带sku
    Route::get('goods/selectgoodssku', 'addon\phone_shop\app\adminapi\controller\goods\Goods@selectGoodsSku');

    // 获取商品SKU规格列表
    Route::get('goods/sku', 'addon\phone_shop\app\adminapi\controller\goods\Goods@sku');

    // 编辑商品规格列表库存
    Route::put('goods/sku/stock', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editGoodsListStock');

    // 编辑商品规格列表价格
    Route::put('goods/sku/price', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editGoodsListPrice');

    // 编辑商品规格列表会员价格
    Route::put('goods/sku/member_price', 'addon\phone_shop\app\adminapi\controller\goods\Goods@editGoodsListMemberPrice');

    // getMemberLevelNoList
    Route::get('member_level/no_lists' , 'addon\phone_shop\app\adminapi\controller\member\MemberLevelNo@lists');

    // 获取商品SKU规格列表
    Route::get('goods/active/count', 'addon\phone_shop\app\adminapi\controller\goods\Goods@getActiveGoodsCount');

    // 获取商品类型
    Route::get('goods/type', 'addon\phone_shop\app\adminapi\controller\goods\Goods@type');

    //商品标签分页列表
    // 规格分组/子项 + 成色等级（上架属性参考数据）
    Route::get('goods/spec/group', 'addon\phone_shop\app\adminapi\controller\goods\Spec@groupList');
    Route::post('goods/spec/group', 'addon\phone_shop\app\adminapi\controller\goods\Spec@groupAdd');
    Route::put('goods/spec/group/:id', 'addon\phone_shop\app\adminapi\controller\goods\Spec@groupEdit');
    Route::delete('goods/spec/group/:id', 'addon\phone_shop\app\adminapi\controller\goods\Spec@groupDel');
    Route::post('goods/spec/item', 'addon\phone_shop\app\adminapi\controller\goods\Spec@itemAdd');
    Route::put('goods/spec/item/:id', 'addon\phone_shop\app\adminapi\controller\goods\Spec@itemEdit');
    Route::delete('goods/spec/item/:id', 'addon\phone_shop\app\adminapi\controller\goods\Spec@itemDel');
    Route::get('goods/grade', 'addon\phone_shop\app\adminapi\controller\goods\Spec@gradeList');
    Route::post('goods/grade', 'addon\phone_shop\app\adminapi\controller\goods\Spec@gradeAdd');
    Route::put('goods/grade/:id', 'addon\phone_shop\app\adminapi\controller\goods\Spec@gradeEdit');
    Route::delete('goods/grade/:id', 'addon\phone_shop\app\adminapi\controller\goods\Spec@gradeDel');
    Route::get('goods/spec/options', 'addon\phone_shop\app\adminapi\controller\goods\Spec@optionsForCategory');

    Route::get('goods/label', 'addon\phone_shop\app\adminapi\controller\goods\Label@pages');

    //商品标签列表
    Route::get('goods/label/list', 'addon\phone_shop\app\adminapi\controller\goods\Label@lists');

    //商品标签详情
    Route::get('goods/label/:id', 'addon\phone_shop\app\adminapi\controller\goods\Label@info');

    //添加商品标签
    Route::post('goods/label', 'addon\phone_shop\app\adminapi\controller\goods\Label@add');

    //编辑商品标签
    Route::put('goods/label/:id', 'addon\phone_shop\app\adminapi\controller\goods\Label@edit');

    //复制商品标签
    Route::post('goods/label/copy/:id', 'addon\phone_shop\app\adminapi\controller\goods\Label@copy');

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
    Route::get('goods/category/tree', 'addon\phone_shop\app\adminapi\controller\goods\Category@tree');

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

    // 获取商品下单选择分页列表
    Route::get('goods/buy/goods/select', 'addon\phone_shop\app\adminapi\controller\goods\Goods@buyGoodsSelect');

    // 获取商品下单已选分页列表
    Route::get('goods/buy/goods/selected', 'addon\phone_shop\app\adminapi\controller\goods\Goods@buyGoodsSelected');

    // 获取商品下单SKU规格列表
    Route::get('goods/buy/sku/select', 'addon\phone_shop\app\adminapi\controller\goods\Goods@buySkuSelect');

    // 批量设置商品
    Route::put('goods/batchSet', 'addon\phone_shop\app\adminapi\controller\goods\Goods@batchSet');

    //获取商品排行榜统计类型
    Route::get('goods/batchSet/dict', 'addon\phone_shop\app\adminapi\controller\goods\Goods@getBatchSetDict');

    /************************************************** 订单相关接口 *****************************************************/
    //交易配置
    Route::post('order/config', 'addon\phone_shop\app\adminapi\controller\order\Config@setConfig');
    Route::get('order/config', 'addon\phone_shop\app\adminapi\controller\order\Config@getConfig');

    //订单列表
    Route::get('order/list', 'addon\phone_shop\app\adminapi\controller\order\Order@lists');

    //订单详情
    Route::get('order/detail/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@detail');
    Route::post('order/device_received/:id', 'addon\phone_shop\app\adminapi\controller\order\Order@confirmDeviceReceived');

    //线下支付订单：资金账户与店内处理
    Route::get('order/offline/capital_accounts', 'addon\phone_shop\app\adminapi\controller\order\Order@offlineCapitalAccounts');
    Route::post('order/offline/process', 'addon\phone_shop\app\adminapi\controller\order\Order@processOffline');

    //订单删除
    Route::post('order/delete', 'addon\phone_shop\app\adminapi\controller\order\Order@delete');

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

    //获取已选订单项总重量
    Route::get('order/select/weight', 'addon\phone_shop\app\adminapi\controller\order\Order@getSelectOrderGoodsWeight');

    //同城配送费用计算
    Route::get('order/delivery/fee', 'addon\phone_shop\app\adminapi\controller\order\Order@getDeliveryFee');

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

    //订单售后 列表
    Route::get('order/refund', 'addon\phone_shop\app\adminapi\controller\refund\Refund@lists');

    //订单售后 详情
    Route::get('order/refund/:id', 'addon\phone_shop\app\adminapi\controller\refund\Refund@detail');

    //订单售后审核
    Route::put('order/refund/audit/:order_refund_no', 'addon\phone_shop\app\adminapi\controller\refund\Refund@auditApply');

    //订单售后审核
    Route::put('order/refund/delivery/:order_refund_no', 'addon\phone_shop\app\adminapi\controller\refund\Refund@auditRefundGoods');

    //订单售后 可退款金额
    Route::get('order/refund/refund_money', 'addon\phone_shop\app\adminapi\controller\refund\Refund@getOrderRefundMoney');

    //订单售后 商家主动退款
    Route::post('order/refund/active', 'addon\phone_shop\app\adminapi\controller\refund\Refund@shopActiveRefund');
    //关闭售后
    Route::put('order/refund/close/:order_refund_no', 'addon\phone_shop\app\adminapi\controller\refund\Refund@closeRefund');

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

    //删除优惠券基于有批量删除
    Route::post('goods/coupon/delete', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@del');

    //优惠券设置状态
    Route::put('goods/coupon/setstatus/:status', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@setCouponStatus');

    //优惠券失效
    Route::put('goods/coupon/invalid', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@couponInvalid');

    //删除优惠券
    Route::delete('goods/coupon/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@del');

    //查询优惠券选择分页列表
    Route::get('goods/coupon/select', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@select');

    //查询选中的优惠券
    Route::get('goods/coupon/selected', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@getSelectedLists');

    //优惠券状态列表
    Route::get('goods/coupon/status', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@getCouponStatus');

    //发送优惠券范围列表
    Route::get('goods/coupon/send/init', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@getSendRangeInit');
    Route::get('goods/coupon/send/pages/:coupon_id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@getSendPages');
    Route::post('goods/coupon/send/:coupon_id', 'addon\phone_shop\app\adminapi\controller\marketing\Coupon@sendCoupon');

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

    //获取商品评价审核状态
    Route::get('goods/evaluate/status', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@status');

    //批量通过
    Route::post('goods/evaluate/batch/adopt', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@batchAdopt');

    //批量拒绝
    Route::post('goods/evaluate/batch/refuse', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@batchRefuse');

    //批量删除
    Route::post('goods/evaluate/batch/del', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@batchDel');

    //商品评价 取消置顶
    Route::put('goods/evaluate/cancel_topping/:id', 'addon\phone_shop\app\adminapi\controller\goods\Evaluate@cancelTopping');
    //校验商品编码
    Route::post('goods/verify/skuno', 'addon\phone_shop\app\adminapi\controller\goods\Goods@verifySkuNo');

    //商品搜索配置
    Route::get('goods/config/search', 'addon\phone_shop\app\adminapi\controller\goods\Config@getSearchConfig');
    Route::post('goods/config/search', 'addon\phone_shop\app\adminapi\controller\goods\Config@setSearchConfig');

    //商品编码配置
    Route::get('goods/config/unique', 'addon\phone_shop\app\adminapi\controller\goods\Config@getUniqueConfig');
    Route::post('goods/config/unique', 'addon\phone_shop\app\adminapi\controller\goods\Config@setUniqueConfig');

    //商品排序配置
    Route::get('goods/config/sort', 'addon\phone_shop\app\adminapi\controller\goods\Config@getSortConfig');
    Route::post('goods/config/sort', 'addon\phone_shop\app\adminapi\controller\goods\Config@setSortConfig');


    Route::get('stat/total', 'addon\phone_shop\app\adminapi\controller\Stat@total');
    Route::get('stat/today', 'addon\phone_shop\app\adminapi\controller\Stat@today');
    Route::get('stat/yesterday', 'addon\phone_shop\app\adminapi\controller\Stat@yesterday');
    Route::get('stat', 'addon\phone_shop\app\adminapi\controller\Stat@stat');
    Route::get('stat/order', 'addon\phone_shop\app\adminapi\controller\Stat@order');
    Route::get('stat/goods', 'addon\phone_shop\app\adminapi\controller\Stat@goods');

    // 发票列表
    Route::get('invoice', 'addon\phone_shop\app\adminapi\controller\order\Invoice@lists');
    //添加发票
    Route::post('invoice/add', 'addon\phone_shop\app\adminapi\controller\order\Invoice@add');

    // 发票信息
    Route::get('invoice/:id', 'addon\phone_shop\app\adminapi\controller\order\Invoice@info');

    // 开票
    Route::put('invoice/:id', 'addon\phone_shop\app\adminapi\controller\order\Invoice@invoicing');

    Route::post('invoice/audit', 'addon\phone_shop\app\adminapi\controller\order\Invoice@audit');

    /************************************************** 限时折扣 *****************************************************/
    //限时折扣列表
    Route::get('active/discount', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@lists');

    //添加
    Route::post('active/discount', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@add');

    //编辑
    Route::put('active/discount/:discount_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@edit');

    //限时折扣商品校验
    Route::post('active/discount/goods/check', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@checkGoods');

    //删除
    Route::delete('active/discount/:discount_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@del');

    //获取限时折扣状态列表
    Route::get('active/discount/status', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@discountStatus');

    //关闭
    Route::put('active/discount/close/:discount_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@close');

    //批量删除
    Route::post('active/discount/batchDelete', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@batchDelete');

    //批量关闭
    Route::post('active/discount/batchClose', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@batchClose');

    //详情-基础信息
    Route::get('active/discount/info/:discount_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@info');

    //详情
    Route::get('active/discount/:discount_id', 'addon\phone_shop\app\adminapi\controller\marketing\Discount@detail');

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

    //积分商城分页列表（用于弹框选择）
    Route::get('active/exchange/select', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@select');

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

    //批量删除
    Route::post('active/exchange/batchDelete', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@batchDelete');

    //批量下架
    Route::post('active/exchange/batchDown', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@batchDown');

    //批量上架
    Route::post('active/exchange/batchUp', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@batchUp');

    //修改排序号
    Route::put('active/exchange/sort/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Exchange@modifySort');

    /************************************************** 新人专享 *****************************************************/
    //新人专享配置
    Route::get('active/newcomer/config', 'addon\phone_shop\app\adminapi\controller\marketing\Newcomer@getConfig');

    //新人专享设置
    Route::put('active/newcomer/config', 'addon\phone_shop\app\adminapi\controller\marketing\Newcomer@setConfig');

    //新人专享商品选择列表
    Route::get('active/newcomer/goods/select', 'addon\phone_shop\app\adminapi\controller\marketing\Newcomer@select');

    //新人专享商品选择已选商品列表
    Route::get('active/newcomer/goods/selectgoodssku', 'addon\phone_shop\app\adminapi\controller\marketing\Newcomer@selectGoodsSku');

    /************************************************** 商品排行榜 *****************************************************/

    // 排行榜配置
    Route::post('good/rank/config', 'addon\phone_shop\app\adminapi\controller\goods\Rank@setRankConfig');

    Route::get('good/rank/config', 'addon\phone_shop\app\adminapi\controller\goods\Rank@getRankConfig');

    // 排行榜分页列表
    Route::get('good/rank', 'addon\phone_shop\app\adminapi\controller\goods\Rank@pages');

    Route::post('good/rank', 'addon\phone_shop\app\adminapi\controller\goods\Rank@add');

    Route::put('good/rank/:id', 'addon\phone_shop\app\adminapi\controller\goods\Rank@edit');

    Route::get('good/rank/:id', 'addon\phone_shop\app\adminapi\controller\goods\Rank@info');

    Route::get('good/rank/dict', 'addon\phone_shop\app\adminapi\controller\goods\Rank@getOptionData');

    Route::delete('good/rank/:id', 'addon\phone_shop\app\adminapi\controller\goods\Rank@del');

    //排行榜修改排序
    Route::put('good/rank/sort', 'addon\phone_shop\app\adminapi\controller\goods\Rank@editSort');

    //排行榜批量删除
    Route::put('good/rank/batchDelete', 'addon\phone_shop\app\adminapi\controller\goods\Rank@batchDelete');

    //获取排行榜分页列表
    Route::get('good/rank/select', 'addon\phone_shop\app\adminapi\controller\goods\Rank@select');

    // 修改排行榜状态
    Route::put('goods/rank/status', 'addon\phone_shop\app\adminapi\controller\goods\Rank@modifyStatus');

    /************************************************** 商品统计 *****************************************************/

    //获取商品统计基本信息
    Route::get('goods/statistics/basic', 'addon\phone_shop\app\adminapi\controller\goods\Statistics@getBasic');

    //获取商品统计图表信息
    Route::get('goods/statistics/trend', 'addon\phone_shop\app\adminapi\controller\goods\Statistics@getTrend');

    //获取商品排行榜信息
    Route::get('goods/statistics/rank', 'addon\phone_shop\app\adminapi\controller\goods\Statistics@getRank');

    //获取统计类型
    Route::get('goods/statistics/type', 'addon\phone_shop\app\adminapi\controller\goods\Statistics@getType');

    //同步商品统计信息
    Route::post('goods/statistics/sync', 'addon\phone_shop\app\adminapi\controller\goods\Statistics@syncStatGoods');

    /************************************************** 满减送 *****************************************************/
    //满减送列表
    Route::get('manjian', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@lists');

    //关闭满减送
    Route::put('manjian/close/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@closeManjian');

    //删除满减送
    Route::delete('manjian/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@del');

    //满减送详情
    Route::get('manjian/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@info');

    //满减送参与会员
    Route::get('manjian/member/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@member');

    //添加满减送
    Route::post('manjian', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@add');

    //编辑满减送
    Route::put('manjian/:id', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@edit');

    //获取编辑数据
    Route::get('manjian/init', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@init');

    //状态
    Route::get('manjian/status', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@status');

    //满减送商品校验
    Route::post('manjian/goods/check', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@checkGoods');

    //满减送批量关闭
    Route::put('manjian/goods/batchClose', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@batchClose');

    //满减送批量删除
    Route::put('manjian/goods/batchDelete', 'addon\phone_shop\app\adminapi\controller\marketing\Manjian@batchDelete');


})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
