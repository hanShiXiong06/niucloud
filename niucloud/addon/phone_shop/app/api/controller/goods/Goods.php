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
            [ 'memory_group', '' ], // 二手机:内存(多值逗号分隔)
            [ 'condition_grade', '' ], // 二手机:成色等级(多值逗号分隔)
            [ 'device_color', '' ], // 设备颜色(多值逗号分隔)
            [ 'battery_range', '' ], // 电池区间:100/95_99/90_94/85_89/80_84/under_80
            [ 'warranty_range', '' ], // 保修区间:expired/under_30/31_60/61_180/181_300/over_300
            [ 'source', '' ], // 货盘来源:1=本站自营 / 主站site_id=主站代理 / 空=全部
            [ 'warehouse', '' ], // 仓库筛选:local=本地仓(自营) / agent=石家庄仓(代理) / 空=全部
            [ 'service_ids', '' ], // 商品服务(多值逗号分隔)
            [ 'in_stock', '' ], // 仅看有货:1
            [ 'arrival_batch_id', 0 ], // 上新通知的导入批次（后端限定本站及可售商品）
        ]);
        return success(( new GoodsService() )->getPage($data));
    }

    /**
     * 商品列表筛选项
     * @return \think\Response
     */
    public function filterOptions()
    {
        $data = $this->request->params([
            [ 'goods_category', '' ],
            [ 'brand_id', '' ],
            [ 'memory_group', '' ],
            [ 'condition_grade', '' ],
            [ 'device_color', '' ],
        ]);
        return success(( new GoodsService() )->getFilterOptions($data));
    }

    /**
     * 仓库切换选项(本地仓/代理仓)
     * @return \think\Response
     */
    public function warehouses()
    {
        return success(( new GoodsService() )->getWarehouseOptions());
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
            [ 'type', '' ], // 来源营销活动类型，discount：限时折扣，newcomer_discount：新人价
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

}
