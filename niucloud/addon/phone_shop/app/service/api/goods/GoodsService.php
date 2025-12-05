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

use addon\phone_shop\app\model\coupon\CouponGoods;
use addon\phone_shop\app\model\goods\Brand;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsCollect;
use addon\phone_shop\app\model\goods\Label;
use addon\phone_shop\app\model\goods\Service;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\site\Site;
use addon\phone_shop\app\model\site\SiteAgent;
use addon\phone_shop\app\service\api\marketing\DiscountService;
use addon\phone_shop\app\model\goods\Memory;
use addon\phone_shop\app\model\goods\MemoryGroup;
use addon\phone_shop\app\model\goods\Category;
use app\model\member\Member;
use core\base\BaseApiService;
use think\facade\Log;
use think\facade\Db;
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
        // 修改字段，与admin端保持一致
        $field = 'goods_id,sub_title,site_id,goods_name,goods_type,brand_id,memory_ids,source,goods_cover,stock,sale_num,status,sort,create_time,update_time,member_discount,is_proxy';

        // 获取当前站点信息
        $site_info = (new Site())->where([['site_id', '=', $this->site_id]])->find();
      
        // 基础SKU查询条件
        if($where['status'] == 'all'){
             $sku_where = [
                 ['goodsSku.is_default', '=', 1],
                ['goodsSku.site_id', 'in', "$this->site_id,100005"]
                
            ];
        }else{
             $sku_where = [
                ['goodsSku.is_default', '=', 1],
                ['goodsSku.site_id', 'in', "$this->site_id,100005"],['status', '=', 1]
                
            ];
        }
        // unset($where['status'] );
        //
       
        
        // 根据client类型设置站点查询条件
        // if ($site_info['client'] == 1  ) {
        //     // 代理商，查询自己的商品
        //     $sku_where[] = ['goods.site_id', '=', $this->site_id];
            
        // } else {
            
        //     // 被代理方，查询自己的商品和代理商的可代理商品
        //     $agent_sites = (new SiteAgent())->where([
        //         ['agent_site_id', '=', $this->site_id],
        //         ['status', '=', 1]
        //     ])->column('site_id');
            
        //     if (!empty($agent_sites)) {
        //         $sku_where[] = function ($query) use ($agent_sites) {
        //             $query->where([
        //                 ['goods.site_id', 'in', "$this->site_id,100005"]
        //             ])->whereOr([
        //                 ['goods.site_id', 'in', $agent_sites],
        //                 ['goods.is_proxy', '=', 1]
        //             ]);
        //         };
        //     } else {
                // $sku_where[] =  ['goods.site_id', 'in', "$this->site_id,100005"];
        //     }
        // }

        // if($where['status'] !== '') {
        //     $sku_where[] = ['status', '=', 1];
        // }

        // 价格区间查询
        if (!empty($where['start_price']) && !empty($where['end_price'])) {
            $money = [$where['start_price'], $where['end_price']];
            sort($money);
            $sku_where[] = ['goodsSku.price', 'between', $money];
        } else if (!empty($where['start_price'])) {
            $sku_where[] = ['goodsSku.price', '>=', $where['start_price']];
        } else if (!empty($where['end_price'])) {
            $sku_where[] = ['goodsSku.price', '<=', $where['end_price']];
        }
        // 内存筛选 memory_id 
        if (!empty($where['memory_id'])) {
            $sku_where[] = ['memory_ids', '=', $where['memory_id']];
        }


        // 关键词搜索
        if (!empty($where['keyword'])) {
            if (strpos($where['keyword'], '#') === 0) {
                $sku_where[] = ['goodsSku.sku_no', 'like', '%' . substr($where['keyword'], 1) . '%'];
            } else {
                $sku_where[] = ['goods_name|sub_title|goodsSku.sku_no', 'like', '%' . $where['keyword'] . '%'];
            }
        }
        
        // SKU编号查询
        if (!empty($where['sku_no'])) {
            $sku_where[] = ['goods_name|goodsSku.sku_no', 'like', '%' . $where['sku_no'] . '%'];
            // $skuNoTrimmed = trim($where['sku_no']);
            // if (strpos($skuNoTrimmed, ' ') !== false) {
            //     $skuNoArray = explode(' ', $skuNoTrimmed);
            //     $sku_where[] = function ($query) use ($skuNoArray) {
            //         $query->whereIn('goodsSku.sku_no', $skuNoArray);
            //     };
            // } else {
            //     $sku_where[] = ['goodsSku.sku_no', '=', $skuNoTrimmed];
            // }
        }

        // 24小时内上传的商品
        if (!empty($where['create_time'])) {
            $current_time = time();
            $twenty_four_hours_ago = $current_time - 86400;
            $sku_where[] = ['goods.create_time', 'between', [$twenty_four_hours_ago, $current_time]];
        }

        // 优惠券商品
        if (!empty($where['coupon_id'])) {
            $coupon_goods_model = new CouponGoods();
            $coupon_list = $coupon_goods_model->where([
                ['coupon_id', '=', $where['coupon_id']]
            ])->field('goods_id,category_id')->select()->toArray();
            if (!empty($coupon_list)) {
                $goods_ids = array_values(array_filter(array_column($coupon_list, 'goods_id')));
                $category_ids = array_values(array_filter(array_column($coupon_list, 'category_id')));
                if (!empty($goods_ids)) {
                    $sku_where[] = ['goods.goods_id', 'in', $goods_ids];
                } elseif (!empty($category_ids)) {
                    $like_arr = [];
                    foreach ($category_ids as $k => $v) {
                        $like_arr[] = "%" . $v . "%";
                    }
                    $sku_where[] = ['goods_category', "like", $like_arr, 'or'];
                }
            }
        }

        // 排序处理
        if (!empty($where['order'])) {
            if($where['order'] == '_time') {
                $order = 'update_time ' . $where['sort'];
            } else {
                $order = $where['order'] . ' ' . $where['sort'];
            }
        } else {
            $order = 'sort asc,create_time desc,update_time desc';
        }

        $search_model = $this->model
            ->withSearch(["brand_id", "goods_category", "label_ids", 'service_ids', "goods_name", "goods_type", "sale_num", "sku_sn"], $where)
            ->field($field)
            ->withJoin(['goodsSku'])
            ->with(['brand_name'])
            ->where($sku_where)
            ->order($order)
            ->append(['goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid','brand_name']);

       
        $list = $this->pageQuery($search_model);

        if (!empty($this->member_id)) {
            $member_info = $this->getMemberInfo();
            foreach ($list['data'] as $k => &$v) {
                if (!empty($v['goodsSku'])) {
                    $v['goodsSku']['member_price'] = $this->getMemberPrice($member_info, $v['member_discount'], $v['goodsSku']['member_price'], $v['goodsSku']['price'],$v['goodsSku']['market_price']);
                }
            }
        }

        foreach ($list['data'] as $k => &$v) {
            if (isset($v['goods_cover_thumb_small']) && isset($v['goods_cover'])) {
                $v['goods_cover_thumb_small'] = $v['goods_cover'];
            }
            if (isset($v['goods_cover_thumb_mid']) && isset($v['goods_cover'])) {
                $v['goods_cover_thumb_mid'] = $v['goods_cover'];
            }
        }

        return $list;
    }

    /**
     * 获取商品详情
     * @param array $data
     * @return array
     */
    public function getDetail(array $data)
    {
        if($this->site_id !== 0 ){
            $sites =  (new Site())-> field('category_status,brand_status,label_status,service_status')->where([['site_id','=', $this->site_id]]) ->findOrEmpty()->toArray();        
        }
        
        $site_id = empty($sites['category_status'] ) ? $this->site_id : $this->site_id.",100005,0";
        $brand_site_id = empty($sites['brand_status'] ) ? $this->site_id : $this->site_id.",0";
        $label_site_id = empty($sites['label_status'] ) ? $this->site_id : $this->site_id.",0";
        $service_site_id = empty($sites['service_status'] ) ? $this->site_id : $this->site_id.",0";
       
        $sku_id = $data[ 'sku_id' ];
        $goods_id = $data[ 'goods_id' ];

        $goods_sku_model = new GoodsSku();

        if (empty($sku_id) && !empty($goods_id)) {
            // 查询默认规格项
            $default_sku_info = $goods_sku_model->where([ [ 'goods_id', '=', $goods_id ], [ 'site_id', 'in', "$site_id" ], [ 'is_default', '=', 1 ] ], 'sku_id')
                ->field('sku_id')->findOrEmpty()->toArray();
            if (!empty($default_sku_info)) {
                $sku_id = $default_sku_info[ 'sku_id' ];
            }
        }

        $field = 'sku_id, sku_name, sku_image, sku_no, goods_id, site_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, sale_num, is_default,member_price';
        
        $info = $goods_sku_model->where([ [ 'site_id', 'in', "$site_id" ], [ 'sku_id', '=', $sku_id ] ])
            ->field($field)
            ->with([
                'goods' => function($query) {
                    $query->withField('goods_id, site_id, goods_name, goods_type, sub_title, goods_cover,memory_ids , goods_category, goods_image,goods_desc,brand_id,label_ids,service_ids, unit, stock, sale_num + virtual_sale_num as sale_num, status,delivery_type,attr_id,attr_format,member_discount,is_discount,poster_id,virtual_receive_type')
                        ->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid', 'goods_cover_thumb_big', 'delivery_type_list', 'goods_image_thumb_small', 'goods_image_thumb_mid', 'goods_image_thumb_big' ]);
                },
                // 商品规格列表
                'skuList' => function($query) {
                    $query->field('sku_id, site_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, is_default,member_price');
                },
                // 商品规格项/规格值列表
                'goodsSpec' => function($query) {
                    $query->field('spec_id, goods_id, spec_name, spec_values');
                },
            ])
            ->append([ 'sku_image_thumb_small', 'sku_image_thumb_mid', 'sku_image_thumb_big' ])
            ->findOrEmpty()->toArray();

        if (!empty($info) && !empty($info[ 'goods' ])) {
            if (!empty($info[ 'goods' ][ 'service_ids' ])) {
                // 商品服务
                $goods_service_model = new Service();
                $info[ 'service' ] = $goods_service_model->where([
                    [ 'site_id', 'in', "$service_site_id" ],
                    [ 'service_id', 'in', $info[ 'goods' ][ 'service_ids' ] ]
                ])->field('service_id, service_name, image, desc')
                    ->select()->toArray();
            }
            if (!empty($info[ 'goods' ][ 'label_ids' ])) {
                // 商品标签
                $goods_label_model = new Label();
                $info[ 'label_info' ] = $goods_label_model->where([
                    [ 'site_id', 'in', "$label_site_id" ],
                    [ 'label_id', 'in', $info[ 'goods' ][ 'label_ids' ] ]
                ])->field('label_id, label_name, memo')
                    ->order('sort desc,create_time desc')->select()->toArray();
            }
            if (!empty($info[ 'goods' ][ 'brand_id' ])) {
                // 商品品牌
                $goods_brand_model = new Brand();
                $info[ 'brand_info' ] = $goods_brand_model->where([
                    [ 'site_id', 'in', "$brand_site_id" ],
                    [ 'brand_id', '=', $info[ 'goods' ][ 'brand_id' ] ]
                ])->field('brand_id, brand_name, logo, desc')
                    ->order('sort desc,create_time desc')
                    ->findOrEmpty()->toArray();
            }

            // 参与限时折扣，查询活动信息
            if ($info[ 'goods' ][ 'is_discount' ] == 1) {
                $discount_service = new DiscountService();
                $info[ 'discount_info' ] = $discount_service->getInfoByGoods($info[ 'goods_id' ]);
                if (!empty($info[ 'discount_info' ])) {
                    $info[ 'discount_info' ][ 'active' ][ 'start_time' ] = strtotime($info[ 'discount_info' ][ 'active' ][ 'start_time' ]);
                    $info[ 'discount_info' ][ 'active' ][ 'end_time' ] = strtotime($info[ 'discount_info' ][ 'active' ][ 'end_time' ]);
                }
            }

            if (!empty($this->member_id)) {
                $goods_collect_model = new GoodsCollect();
                $collect_info = $goods_collect_model->where([ [ 'site_id', 'in', "$site_id" ], [ 'member_id', '=', $this->member_id ], [ 'goods_id', '=', $info[ 'goods_id' ] ] ])->findOrEmpty()->toArray();
                if (!empty($collect_info)) {
                    $info[ 'goods' ][ 'is_collect' ] = 1;
                } else {
                    $info[ 'goods' ][ 'is_collect' ] = 0;
                }

                // 查询会员价
                $member_info = $this->getMemberInfo();
                $info[ 'member_price' ] = $this->getMemberPrice($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'member_price' ], $info[ 'price' ],$info[ 'market_price' ]);

                $this->getMemberPriceByList($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'skuList' ], $info['market_price']);

            } else {
                $info[ 'goods' ][ 'is_collect' ] = 0;
                $info[ 'member_price' ] = $info[ 'price' ];
                foreach ($info[ 'skuList' ] as &$v) {
                    $v[ 'member_price' ] = $info[ 'price' ];
                }
            }
        }
        return $info;
    }

    /**
     * 获取商品规格信息，切换规格
     * @param int $sku_id
     * @return array
     */
    public function getSku(int $sku_id)
    { 
        if($this->site_id !== 0 ){
        $sites =  (new Site())-> field('category_status')->where([['site_id','=', $this->site_id]]) ->findOrEmpty()->toArray();
        
         }
    
    $site_id = empty($sites['category_status'] ) ? $this->site_id : $this->site_id.",0";

        $field = 'site_id,sku_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, sale_num, is_default,member_price';

        $goods_sku_model = new GoodsSku();
        

        $info = $goods_sku_model->where([ [ 'site_id', 'in', "$site_id" ], [ 'sku_id', '=', $sku_id ] ])
            ->field($field)
            ->with([
                // 商品主表
                'goods' => function($query) {
                    $query->withField('goods_id, goods_name, goods_type, sub_title,memory_ids,goods_cover, unit, stock, sale_num + virtual_sale_num as sale_num, status,member_discount,is_discount')
                        ->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid', 'goods_cover_thumb_big' ]);
                },
                // 商品规格列表
                'skuList' => function($query) {
                    $query->field('site_id, sku_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, is_default,member_price');
                },
                // 商品规格项/规格值列表
                'goodsSpec' => function($query) {
                    $query->field('spec_id, goods_id, spec_name, spec_values');
                },
            ])
            ->append([ 'sku_image_thumb_small', 'sku_image_thumb_mid', 'sku_image_thumb_big' ])
            ->findOrEmpty()->toArray();
        if (!empty($this->member_id)) {
            $member_info = $this->getMemberInfo();

            $info[ 'member_price' ] = $this->getMemberPrice($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'member_price' ], $info[ 'price' ],$info[ 'market_price' ]);

            $this->getMemberPriceByList($member_info, $info[ 'goods' ][ 'member_discount' ], $info[ 'skuList' ],$info['market_price']);

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
        $field = 'goods_id,site_id,goods_name,sub_title,goods_category,goods_type,goods_cover,unit,status,sale_num + goods.virtual_sale_num as sale_num,member_discount,is_discount';

        $sku_where = [
            [ 'goodsSku.is_default', '=', 1 ],
            [ 'goodsSku.site_id', '=', $this->site_id ],
            [ 'status', '=', 1 ]
        ];

        if (!empty($where[ 'goods_ids' ])) {
            $sku_where[] = [ 'goods.goods_id', 'in', $where[ 'goods_ids' ] ];
        }

        // 参数过滤
        if (!empty($where[ 'order' ]) && in_array($where[ 'order' ], [ 'sale_num', 'price' ])) {
            $order = $where[ 'order' ] . ' desc';
        } else {
            $order = 'sort desc,create_time desc';
        }

        $list = $this->model
            ->withSearch([ "goods_category", "label_ids", 'service_ids' ], $where)
            ->field($field)
            ->withJoin([ 'goodsSku' ])
            ->where($sku_where)->order($order)->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid' ])
            ->limit($where[ 'num' ])
            ->select()->toArray();
        if (!empty($this->member_id)) {
            $member_info = $this->getMemberInfo();
            foreach ($list as $k => &$v) {
                if (!empty($v[ 'goodsSku' ])) {
                    $v[ 'goodsSku' ][ 'member_price' ] = $this->getMemberPrice($member_info, $v[ 'member_discount' ], $v[ 'goodsSku' ][ 'member_price' ], $v[ 'goodsSku' ][ 'price' ],$v[ 'goodsSku' ][ 'market_price' ]);
                }
                
            }
        }

        foreach ($list as $k => &$v) {
           
            // 替换缩略图字段为cover的值
            if (isset($v['goods_cover_thumb_small']) && isset($v['goods_cover'])) {
               $v['goods_cover_thumb_small'] = $v['goods_cover'];
           }
           if (isset($v['goods_cover_thumb_mid']) && isset($v['goods_cover'])) {
               $v['goods_cover_thumb_mid'] = $v['goods_cover'];
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
                'memberLevelData' => function($query) {
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
    public function getMemberPrice($member_info, $member_discount, $member_price, $price,$market_price=0)
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
        
       // log:write('price-----------会员的身份'.$member_discount);
        if ($member_discount == 'discount') {
            // 按照会员等级折扣计算

            // 默认按会员享受折扣计算
            if (!empty($member_info[ 'memberLevelData' ][ 'level_benefits' ])
                && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ])
                && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ][ 'is_use' ])) {

                $price = number_format($price * $member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ][ 'discount' ] / 10, 2, '.', '');
            }

        } elseif ($member_discount == 'fixed_price') {
            // 指定会员价
            // if (!empty($member_price)) {
            //     $member_price = json_decode($member_price, true);
            //     if (!empty($member_price[ 'level_' . $member_info[ 'member_level' ] ])) {
            //         $member_level_price = $member_price[ 'level_' . $member_info[ 'member_level' ] ];
            //         $price = number_format($member_level_price, 2, '.', '');
            //     }
            // }
            $price = $market_price;
        }

        return $price;

    }

    /**
     * 查询商品的会员价
     * @param $member_infowx20120555b35b6174
     * @param string $member_discount 会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price
     * @param $sku_list
     * @return int
     */
    public function getMemberPriceByList($member_info, $member_discount, &$sku_list, $market_price)
    {
        // Log::write('会员价格是------'. $market_price."------会员身份-----".$member_info."----会员身份-----".$member_discount."----会员身份-----".$sku_list) ;
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

            // $v[ 'member_price' ] = $v[ 'price' ];
            if ($is_default) {
                $v[ 'member_price' ] = $v[ 'price' ];
            } else {
                if ($member_discount == 'discount') {
                    // 按照会员等级折扣计算

                    // 默认按会员享受折扣计算
                    if (!empty($member_info[ 'memberLevelData' ][ 'level_benefits' ])
                        && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ])
                        && !empty($member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ][ 'is_use' ])) {
                        $v[ 'member_price' ] = number_format($v[ 'price' ] * $member_info[ 'memberLevelData' ][ 'level_benefits' ][ 'discount' ][ 'discount' ] / 10, 2, '.', '');
                    }

                } elseif ($member_discount == 'fixed_price') {
                    // 指定会员价
                    // if (!empty($v[ 'member_price' ])) {
                    //     $member_price = json_decode($v[ 'member_price' ], true); // 会员价，json格式，指定会员价
                    //     // var_dump($member_price);
                    //     if (!empty($member_price[ 'level_' . $member_info[ 'member_level' ] ])) {
                    //         $member_level_price = $member_price[ 'level_' . $member_info[ 'member_level' ] ];
                    //         $v[ 'member_price' ] = number_format($member_level_price, 2, '.', '');
                    //     } else {
                    //         $v[ 'member_price' ] = $v[ 'price' ];
                    //     }
                    // }
                     $v[ 'member_price' ]= $market_price;
                    
                }
            }
        }

        return $sku_list;

    }

    /**
     * operationGoods
     * 操作商品的上下架
     * 参数是 goods_id
     * @param $goods_id
     * @return array
    */
    public function operationGoods($data)
    {
        $goods_id = $data[ 'goods_id' ];
       
        if (empty($goods_id)) {
            return error('', '请选择商品');
        }
        // 查询 goods_id 的状态 
        $field = 'status' ;
        $goods_info = $this->model->where('goods_id','=',$goods_id)->field($field)->find()->toArray();
        $this->model->where(  'goods_id' , "=" , $goods_id)-> update( [ 'status' => !$goods_info['status']]);
        return true;
    }
    
    /**
     * 添加商品
     * @param $data
     * @return array
   */
    public function add(array $data)
    {

        // 将 $data[ 'goods_image' ] 转为 xxx,22,333

        // return $data[ 'goods_image' ];
        try {
            Db::startTrans();
            $goods_sku_model = new GoodsSku();
            // $goods_spec_model = new GoodsSpec();

            
            // 商品封面
            if (!empty($data[ 'goods_image' ])) $data[ 'goods_cover' ] = $data['goods_image'][0];
            // 获取当前商品的数量
            $count = $this->model->getGoodsCount();
          
            $goods_data = [
                'site_id' => $this->site_id,
                'goods_no'=>$this->model->getLastGoodsId()+10001,
                'goods_name' => $data[ 'goods_name' ],
                'sub_title' => $data[ 'goods_desc' ],
                'goods_type' => $data[ 'goods_type' ],
                'goods_cover' => $data[ 'goods_cover' ],
                'goods_image' => implode(',', $data[ 'goods_image' ]),
                'goods_category' => array_map(function($item) { return (string) $item; }, $data[ 'goods_category' ]),
                'goods_desc' => $data[ 'goods_desc' ],
                'brand_id' => $data[ 'brand_id' ],
                'label_ids' => array_map(function($item) { return (string) $item; }, $data[ 'label_ids' ]),
                'service_ids' => array_map(function($item) { return (string) $item; }, $data[ 'service_ids' ]),
                'unit' => $data[ 'unit' ],
                'stock' => $data[ 'stock' ],
                'virtual_sale_num' => $data[ 'virtual_sale_num' ],
                'status' => $data[ 'status' ],
                'sort' => $data[ 'sort' ],
                'attr_id' => $data[ 'attr_id' ],
                'attr_format' => $data[ 'attr_format' ],
                'delivery_type' => $data[ 'delivery_type' ],
                'is_free_shipping' => $data[ 'is_free_shipping' ],
                'fee_type' => $data[ 'fee_type' ],
                'delivery_money' => $data[ 'delivery_money' ],
                'delivery_template_id' => $data[ 'delivery_template_id' ],
                'supplier_id' => $data[ 'supplier_id' ],
                'member_discount' => $data[ 'member_discount' ],
                'poster_id' => $data[ 'poster_id' ],
                'is_proxy' => $data[ 'is_proxy' ],
                'memory_ids' => $data[ 'memory_ids' ],
                'create_time' => time()
            ];
            $res = $this->model->create($goods_data);

            $sku_data = [];
            if ($data['spec_type'] == 'single') {
                // 单规格
                $sku_data = [
                    'site_id' => $this->site_id,
                    'sku_name' => '',
                    'sku_image' => $data['goods_cover'],
                    'sku_no' => $data['sku_no'],
                    'goods_id' => $res->goods_id,
                    'sku_spec_format' => '', // sku规格格式
                    'price' => $data['price'],
                    'market_price' => $data['market_price'],
                    'sale_price' => $data['price'],
                    'cost_price' => $data['cost_price'],
                    'stock' => $data['stock'],
                    'weight' => $data['weight'],
                    'volume' => $data['volume'],
                    'is_default' => 1
                ];
                $goods_sku_model->save($sku_data);
            } 
            
            

            Db::commit();

            event('AfterGoodsEdit', [
                'goods_id' => $res->goods_id,
                'goods_data' => $goods_data,
                'sku_data' => $sku_data
            ]);

            return $res->goods_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    // 修改商品
    public function update(int $goods_id, array $data)
    {
        try {
            Db::startTrans();
            $goods_sku_model = new GoodsSku();

            // 准备商品主表数据
            $goods_data = [];

            // 商品封面
            if (!empty($data['goods_image'])) {
                $goods_data['goods_cover'] = $data['goods_image'][0];
                $goods_data['goods_image'] = implode(',', $data['goods_image']);
            }

            // 处理数组字段，确保它们是字符串格式
            if (!empty($data['goods_category'])) {
                $goods_data['goods_category'] = array_map(function($item) { return (string) $item; }, $data['goods_category']);
            }
            if (!empty($data['label_ids'])) {
                $goods_data['label_ids'] = array_map(function($item) { return (string) $item; }, $data['label_ids']);
            }
            if (!empty($data['service_ids'])) {
                $goods_data['service_ids'] = array_map(function($item) { return (string) $item; }, $data['service_ids']);
            }

            // 复制商品基本信息字段
            $goods_fields = [
                'goods_name', 'sub_title', 'goods_type', 'brand_id', 
                'unit', 'status', 'sort', 'attr_id', 'attr_format',
                'delivery_type', 'is_free_shipping', 'fee_type',
                'delivery_money', 'delivery_template_id', 'supplier_id',
                'member_discount', 'poster_id', 'is_proxy', 'memory_ids',
                'goods_desc'
            ];

            foreach ($goods_fields as $field) {
                if (isset($data[$field])) {
                    $goods_data[$field] = $data[$field];
                }
            }

            $goods_data['update_time'] = time();
            
            // 更新商品基本信息
            $res = $this->model->where('goods_id', '=', $goods_id)->update($goods_data);

            // 更新SKU信息
            if (!empty($data['sku_no'])) {
                $sku_data = [
                    'sku_no' => $data['sku_no']
                ];

                // 只有在提供了这些字段时才更新
                $sku_fields = ['price', 'market_price', 'cost_price', 'stock', 'weight', 'volume'];
                foreach ($sku_fields as $field) {
                    if (isset($data[$field])) {
                        $sku_data[$field] = $data[$field];
                    }
                }

                if (isset($data['price'])) {
                    $sku_data['sale_price'] = $data['price'];
                }

                if (!empty($goods_data['goods_cover'])) {
                    $sku_data['sku_image'] = $goods_data['goods_cover'];
                }
                
                $goods_sku_model->where([
                    ['goods_id', '=', $goods_id],
                    ['is_default', '=', 1]
                ])->update($sku_data);
            }

            Db::commit();

            event('AfterGoodsEdit', [
                'goods_id' => $goods_id,
                'goods_data' => $goods_data,
                'sku_data' => isset($sku_data) ? $sku_data : []
            ]);

            return $res;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    // getMemoryList 
    public function getMemoryList( array $where = [], $field = 'spec_id, spec_name,    site_id,  sort, create_time, update_time')
    {
    // $where['id'] 是 分类id
    // 先获取 分类的 memory_group
    $memory_group = (new Category())->where([ ['category_id', '=', $where['id']]])->value('memory_group');
   
    if($this->site_id !== 0 ){
        $sites = (new Site())->field('memory_group_status')->where([['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
    }
    $site_id = empty($sites['memory_group_status']) ? $this->site_id : $this->site_id.",0";
       
    if(!empty($memory_group)){
       // 先查询 $where['id'] 在 memory_group 表中 获取 memory_ids
       $memory_ids = (new MemoryGroup())->where([['site_id', 'in',  $site_id], ['group_id', 'in', $memory_group ]])->value('memory_ids');
       if($memory_ids){
        $where['memory_ids'] = $memory_ids;
       }
       
      
    }else{
        return [];
    }
   
       // 然后通过 memory_ids 查询 memory 表 获取 spec_name
       $memory_list = (new Memory())->where([['site_id', 'in', $site_id], ['spec_id', 'in', $where['memory_ids']]])->field('spec_id, spec_name')->select()->toArray();

       return $memory_list;
        $order = 'sort desc,spec_id desc';
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_status')->where([['site_id', 'in', $site_id]])->findOrEmpty()->toArray();
        }
        
    
        $list=  $this->model->where([['site_id', 'in', $site_id]])->withSearch(["spec_name"])
            ->field($field)
            
            ->order($order)
            ->select()
            ->toArray();

            // 将其整理 去除不必要的字段
            // $list = array_map(function($item) {
            //     return [
            //         'memory_id' => $item['spec_id'],
            //         'memory_name' => $item['spec_name'],
            //     ];
            // }, $list);

        return $list;
    }

}
