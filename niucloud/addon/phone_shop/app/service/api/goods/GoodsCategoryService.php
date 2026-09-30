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

use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\service\core\goods\CoreGoodsCategoryService;
use core\base\BaseApiService;

/**
 *  商品分类服务层
 */
class GoodsCategoryService extends BaseApiService
{
    /**
     * 当前请求内可展示商品涉及的分类（包含全部祖先节点）。
     * 同一次请求可能既消费树又消费列表，缓存后避免重复扫描商品分类字段。
     * @var array|null
     */
    private ?array $visibleCategoryIds = null;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Category();
    }

    /**
     * 查询商品分类树结构
     * @param string $field
     * @param string $order
     * @return array
     */
    public function getTree()
    {
        $categoryIds = $this->getVisibleCategoryIds();
        if (empty($categoryIds)) return [];

        return ( new CoreGoodsCategoryService() )->getTree([
            [ 'is_show', '=', 1 ],
            [ 'site_id', '=', $this->site_id ],
            [ 'category_id', 'in', $categoryIds ]
        ], 'category_id,category_name,image,level,pid,category_full_name');
    }

    /**
     * 获取商品分类配置
     * @return array
     */
    public function getGoodsCategoryConfig()
    {
        return ( new CoreGoodsCategoryService() )->getGoodsCategoryConfig($this->site_id);
    }

    /**
     * 获取商品分类列表
     * @param array $where
     * @param string $field
     * @return array
     */
    public function getList(array $where = [], $field = 'category_id,category_name,image,level,pid,category_full_name')
    {
        $categoryIds = $this->getVisibleCategoryIds();
        if (empty($categoryIds)) return [];

        $order = 'sort desc,create_time desc';
        return $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'is_show', '=', 1 ],
            [ 'category_id', 'in', $categoryIds ]
        ])->withSearch([ "category_name", 'level', 'category_id', 'pid' ], $where)->field($field)->order($order)->select()->toArray();
    }

    /**
     * 只返回商城当前真正可展示商品所占用的分类。
     *
     * 商品分类以 JSON 路径保存。这里只查询去重后的路径，而不是加载全部商品，
     * 即使站点有数万件商品，通常也只需要解析几十种分类组合。
     */
    private function getVisibleCategoryIds(): array
    {
        if ($this->visibleCategoryIds !== null) return $this->visibleCategoryIds;

        $masterSiteId = ( new \addon\phone_shop\app\service\core\agent\AgentConfigService() )->getMasterSiteId();
        $isAgentSite = $this->site_id !== $masterSiteId;
        $goodsQuery = ( new Goods() )->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'status', '=', 1 ],
            [ 'sale_status', '=', 'available' ],
            [ 'is_gift', '=', GoodsDict::NOT_IS_GIFT ],
            [ 'delete_time', '=', 0 ]
        ]);
        if ($isAgentSite) {
            // 新版跟随商品可在从站直接购买；来源条件仅兼容尚未全量校准的历史副本。
            $goodsQuery->where(function ($query) use ($masterSiteId) {
                $query->where('is_online_sellable', '=', 1)
                    ->whereOr('source', '=', (string)$masterSiteId);
            });
        } else {
            $goodsQuery->where('is_online_sellable', '=', 1);
        }

        $categoryValues = $goodsQuery->whereNotNull('goods_category')->where('goods_category', '<>', '')
            ->group('goods_category')->column('goods_category');

        $categoryIds = [];
        foreach ($categoryValues as $value) {
            if (is_array($value)) {
                $path = $value;
            } else {
                $path = json_decode((string)$value, true);
                if (!is_array($path)) {
                    $path = preg_split('/\s*[,，]\s*/u', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                }
            }
            foreach ($path as $categoryId) {
                $categoryId = (int)$categoryId;
                if ($categoryId > 0) $categoryIds[$categoryId] = $categoryId;
            }
        }

        if (empty($categoryIds)) return $this->visibleCategoryIds = [];

        // 兼容历史商品只保存末级分类的情况：补齐祖先节点，保证返回的树不会断层。
        $categories = $this->model->where([ [ 'site_id', '=', $this->site_id ] ])
            ->field('category_id,pid')->select()->toArray();
        $parentMap = [];
        foreach ($categories as $category) {
            $parentMap[(int)$category['category_id']] = (int)$category['pid'];
        }
        foreach (array_values($categoryIds) as $categoryId) {
            $parentId = $parentMap[$categoryId] ?? 0;
            $guard = 0;
            while ($parentId > 0 && $guard++ < 20) {
                $categoryIds[$parentId] = $parentId;
                $parentId = $parentMap[$parentId] ?? 0;
            }
        }

        return $this->visibleCategoryIds = array_values($categoryIds);
    }

}
