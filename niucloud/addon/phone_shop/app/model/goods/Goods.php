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

namespace addon\phone_shop\app\model\goods;

use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\model\site\Site;
use addon\phone_shop\app\model\active\ActiveGoods;
use addon\phone_shop\app\service\core\delivery\CoreDeliveryService;
use app\dict\sys\FileDict;
use core\base\BaseModel;
use think\db\Query;
use think\model\concern\SoftDelete;
// use addon\phone_shop\app\model\goods\GoodsCategory;
use addon\phone_shop\app\model\goods\Brand;

/**
 * 商品模型
 * Class Goods
 * @package addon\phone_shop\app\model\goods
 */
class Goods extends BaseModel
{

    use SoftDelete;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'goods_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'phone_shop_goods';

    /**
     * 定义软删除标记字段.
     * @var string
     */
    protected $deleteTime = 'delete_time';

    /**
     * 定义软删除字段的默认值
     * @var int
     */
    protected $defaultSoftDelete = 0;

    // 设置json类型字段
    protected $json = [ 'goods_category', 'label_ids', 'service_ids', 'delivery_type' ];

    // 设置JSON数据返回数组
    protected $jsonAssoc = true;

    /**
     * 状态字段转化
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getGoodsTypeNameAttr($value, $data)
    {
        if (!empty($data[ 'goods_type' ])) {
            return GoodsDict::getType($data[ 'goods_type' ])[ 'name' ] ?? '';
        }
        return '';
    }

    /**
     * 状态字段转化
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getGoodsEditPathAttr($value, $data)
    {
        if (!empty($data[ 'goods_type' ])) {
            return GoodsDict::getType($data[ 'goods_type' ])[ 'path' ] ?? '';
        }
        return '';
    }

    /**
     * 状态字段转化：配送方式
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getDeliveryTypeListAttr($value, $data)
    {
        if (!empty($data[ 'delivery_type' ])) {
            $deliver_list = ( new CoreDeliveryService() )->getDeliveryConfig($data[ 'site_id' ]);
            $res = [];
            foreach ($data[ 'delivery_type' ] as $k => $v) {
                if (isset($deliver_list[ $v ]) && $deliver_list[ $v ][ 'status' ] == 1) {
                    $res[ $v ] = $deliver_list[ $v ];
                }
            }
            return $res;
        }
        return '';
    }

    /**
     * 获取封面缩略图（小）
     */
    public function getGoodsCoverThumbSmallAttr($value, $data)
    {
        if (isset($data[ 'goods_cover' ]) && $data[ 'goods_cover' ] != '') {
            return get_thumb_images($data[ 'site_id' ], $data[ 'goods_cover' ], FileDict::SMALL);
        }
        return [];
    }

    /**
     * 获取封面缩略图（中）
     */
    public function getGoodsCoverThumbMidAttr($value, $data)
    {
        if (isset($data[ 'goods_cover' ]) && $data[ 'goods_cover' ] != '') {
            return get_thumb_images($data[ 'site_id' ], $data[ 'goods_cover' ], FileDict::MID);
        }
        return [];
    }

    /**
     * 获取封面缩略图（大）
     */
    public function getGoodsCoverThumbBigAttr($value, $data)
    {
        if (isset($data[ 'goods_cover' ]) && $data[ 'goods_cover' ] != '') {
            return get_thumb_images($data[ 'site_id' ], $data[ 'goods_cover' ], FileDict::BIG);
        }
        return [];
    }

    /**
     * 获取商品图片缩略图（小）
     */
    public function getGoodsImageThumbSmallAttr($value, $data)
    {
        if (isset($data[ 'goods_image' ]) && $data[ 'goods_image' ] != '') {
            $goods_image = explode(',', $data[ 'goods_image' ]);
            $img_arr = [];
            foreach ($goods_image as $k => $v) {
                $img = get_thumb_images($data[ 'site_id' ], $v, FileDict::SMALL);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }

    /**
     * 获取商品图片缩略图（中）
     */
    public function getGoodsImageThumbMidAttr($value, $data)
    {
        if (isset($data[ 'goods_image' ]) && $data[ 'goods_image' ] != '') {
            $goods_image = explode(',', $data[ 'goods_image' ]);
            $img_arr = [];
            foreach ($goods_image as $k => $v) {
                $img = get_thumb_images($data[ 'site_id' ], $v, FileDict::MID);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }

    /**
     * 获取商品图片缩略图（大）
     */
    public function getGoodsImageThumbBigAttr($value, $data)
    {
        if (isset($data[ 'goods_image' ]) && $data[ 'goods_image' ] != '') {
            $goods_image = explode(',', $data[ 'goods_image' ]);
            $img_arr = [];
            foreach ($goods_image as $k => $v) {
                $img = get_thumb_images($data[ 'site_id' ], $v, FileDict::BIG);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }

    /**
     * 获取商品分类
     */
    public function getGoodsCategoryAttr($value, $data)
    {
        if (!is_array($value)) {
            $value = json_decode($value, true);
        }
        if (!empty($value)) {
            return array_map(function($item) {
                return (int) $item;
            }, $value);
        }
        return [];
    }

    public function getGoodsLabelNameAttr($value, $data)
    {
        if (isset($data[ 'label_ids' ]) && !empty($data[ 'label_ids' ])) {
            $goods_label_model = new Label();
            return $goods_label_model->where([
                [ 'label_id', 'in', $data[ 'label_ids' ] ]
            ])->field('label_id, label_name, memo')
                ->select()->toArray();
        }

    }

    /**
     * 搜索器:商品商品id
     * @param $value
     * @param $data
     */
    public function searchGoodsIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("goods_id", $value);
        }
    }
    

    /**
     * 搜索器:商品商品名称
     * @param $value
     * @param $data
     */
    public function searchGoodsNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("goods_name", "like", "%" . $this->handelSpecialCharacter($value) . "%");
        }
    }

    /**
     * 搜索器:商品副标题
     * @param $value
     * @param $data
     */
    public function searchSubTitleAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("sub_title", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:商品商品类型
     * @param $value
     * @param $data
     */
    public function searchGoodsTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("goods_type", $value);
        }
    }

    /**
     * 搜索器:商品商品品牌id
     * @param $value
     * @param $data
     */
    public function searchBrandIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("brand_id", $value);
        }
    }

    /**
     * 搜索器:商品商品分类
     * @param $value
     * @param $data
     */
    public function searchGoodsCategoryAttr(Query $query, $value, $data)
    {
        if ($value) {
            // 将传入的分类ID字符串转为数组
            $category_ids = is_array($value) ? $value : explode(',', $value);
            
            // 构建查询条件
            $query->where(function ($query) use ($category_ids) {
                foreach ($category_ids as $category_id) {
                    // 使用 JSON_CONTAINS 函数查询，支持精确匹配数组中的值
                    $query->whereOr("JSON_CONTAINS(goods_category, '\"$category_id\"')");
                }
            });
        }
    }

    /**
     * 搜索器:商品标签组
     * @param $value
     * @param $data
     */
    public function searchLabelIdsAttr($query, $value, $data)
    {
        if ($value) {
            if (is_array($value)) {
                $temp_where = array_map(function($item) { return '%"' . $item . '"%'; }, $value);
            } else {
                $temp_where = [ '%"' . $value . '"%' ];
            }
            $query->where('label_ids', 'like', $temp_where, 'or');
        }
    }

    /**
     * 搜索器:商品服务
     * @param $value
     * @param $data
     */
    public function searchServiceIdsAttr($query, $value, $data)
    {
        if ($value) {
            if (is_array($value)) {
                $temp_where = array_map(function($item) { return '%"' . $item . '"%'; }, $value);
            } else {
                $temp_where = [ '%"' . $value . '"%' ];
            }
            $query->where('service_ids', 'like', $temp_where, 'or');
        }
    }

    /**
     * 搜索器:商品支持的配送方式
     * @param $value
     * @param $data
     */
    public function searchDeliveryTypeAttr($query, $value, $data)
    {
        if ($value) {
            if (gettype($value) == 'array') {
                $like_arr = [];
                foreach ($value as $k => $v) {
                    $like_arr[] = "%" . $v . "%";
                }
                $query->where("delivery_type", "like", $like_arr, 'or');
            } else {
                $query->where("delivery_type", "like", '%' . $value . '%');
            }
        }
    }

    /**
     * 搜索器:商品销量
     * @param $value
     * @param $data
     */
    public function searchSaleNumAttr($query, $value, $data)
    {
        if (!empty($data[ 'start_sale_num' ]) && !empty($data[ 'end_sale_num' ])) {
            $money = [ $data[ 'start_sale_num' ], $data[ 'end_sale_num' ] ];
            sort($money);
            $query->where('goods.sale_num', 'between', $money);
        } else if (!empty($data[ 'start_sale_num' ])) {
            $query->where('goods.sale_num', '>=', $data[ 'start_sale_num' ]);
        } else if (!empty($data[ 'end_sale_num' ])) {
            $query->where('goods.sale_num', '<=', $data[ 'end_sale_num' ]);
        }

    }

    /**
     * 搜索器:商品商品状态（1.正常0下架）
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }

    /**
     * 搜索器:供应商id
     * @param $value
     * @param $data
     */
    public function searchSupplierIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("supplier_id", $value);
        }
    }

    /**
     * 关联默认商品规格
     * @return \think\model\relation\HasOne
     */
    public function goodsSku()
    {
        return $this->hasOne(GoodsSku::class, 'goods_id', 'goods_id');
    }

    // brand_name
    public function brandName()
    {
        return $this->hasOne( Brand::class, 'brand_id', 'brand_id')
        ->withField(' brand_name, logo');
    }

    // goods_category
    // public function goodsCategory()
    // {
    //     return $this->hasOne(GoodsCategory::class, 'goods_category_id', 'goods_category');
    // }

    /**
     * 关联活动
     * @return \think\model\relation\HasOne
     */
    public function activeGoods()
    {
        return $this->hasOne(ActiveGoods::class, 'goods_id', 'goods_id');
    }

    /**
     * 关联商品规格列表
     * @return \think\model\relation\HasMany
     */
    public function skuList()
    {
        return $this->hasMany(GoodsSku::class, 'goods_id', 'goods_id');
    }

    /**
     * 关联商品规格列表
     * @return \think\model\relation\HasMany
     */
    public function goodsSpec()
    {
        return $this->hasMany(GoodsSpec::class, 'goods_id', 'goods_id');
    }

    /**
     * 关联商品品牌
     * @return \think\model\relation\HasOne
     */
    public function brand()
    {
        return $this->hasOne(Brand::class, 'brand_id', 'brand_id')
            ->joinType('left')
            ->withField('brand_id, brand_name, logo, desc');
    }
    // site_name
    // 查询站点名
    //  string
    public function getSiteNameAttr($value, $data)
    {
        if (isset($data['site_id'])) {
            $site = (new Site())->where('site_id', '=', $data['site_id'])->value('site_name');
            return $site ?: '';
        }
        return '';
    }

    // protected $append = ['site_name'];
    // 

    /**
     * 关联商品参数
     * @return \think\model\relation\HasOne
     */
    public function attr()
    {
        return $this->hasOne(Attr::class, 'attr_id', 'attr_id')
            ->joinType('left')
            ->withField('attr_id, attr_name, attr_value_format, sort');
    }
  

    /**
     * 获取商品的总数量 
     * 
    */
    public static function getGoodsCount($condition = [])
    {
        // 获取所有的商品数量
        $count = self::where($condition)->count();
        return $count;
    }
    public function getBrandNameAttr($value, $data)
    {
        // 如果已经加载了 brand 关联，则直接返回 brand_name
        if (isset($this->brand) && $this->brand) {
            return $this->brand->brand_name;
        }
        return null;
    }
    protected $append = ['brand_name'];  // 确保在模型数组/JSON表示中包含 brand_name

    public function searchSkuNoAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("sku_no", "like", "%". $value. "%");
        }
    }
    public function skuNo(){
        return $this->hasOne(GoodsSku::class,'sku_no','sku_no');
    }

    /**
     * 获取商品的最后一条的 goods_id 值
     * 
    */
    public static function getLastGoodsId()
    {
        $goods_id = self::order('goods_id desc')->limit(1)->value('goods_id');
        if (empty($goods_id)) {
            $goods_id = 0;
        }
        return $goods_id;
    }
    
    
}
