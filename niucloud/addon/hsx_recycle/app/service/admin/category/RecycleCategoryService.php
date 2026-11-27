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

namespace addon\hsx_recycle\app\service\admin\category;

use addon\hsx_recycle\app\model\category\RecycleCategory;
use addon\hsx_recycle\app\service\core\category\CoreRecycleCategoryService;
use addon\hsx_recycle\app\model\category\RecycleCategoryConfig;
use addon\hsx_recycle\app\model\category\RecycleCategoryQuoteHistory;
use app\dict\site\SiteDict;
use app\service\admin\auth\AuthService;
use app\service\admin\auth\AuthSiteService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 二手机分类服务层
 * Class RecycleCategoryService
 * @package addon\hsx_recycle\app\service\admin\recycle_category
 */
class RecycleCategoryService extends BaseAdminService
{
    private const CACHE_TTL = 86400;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleCategory();
    }

    /**
     * 获取二手机分类列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'category_id,site_id,category_name,image,level,pid,category_full_name,is_show,sort,create_time,update_time,images, need_vip';
        $order = '';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["category_name","level","pid","category_full_name","is_show","sort","create_time","update_time"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 查询商品分类树结构
     * @param string|null $date 指定日期（YYYY-MM-DD）查看该日及之前最新的报价单快照；为空取当前最新
     * @return array
     */
    public function getTree(?string $date = null)
    {
        if ($date === null) {
            $cacheKey = $this->cacheKey('tree');
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if($this->site_id !== 0) {
            $config = (new RecycleCategoryConfig())->where([
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
        }

        $site_id = empty($config) || empty($config['is_enable']) ? $this->site_id : $this->site_id.",0";
        $tree = (new CoreRecycleCategoryService())->getTree([['site_id', 'in', "{$site_id}"]]);

        if ($date) {
            $this->applyHistoryImagesToTree($tree, $date);
        }

        if ($date === null) {
            Cache::tag($this->cacheTag())->set($cacheKey, $tree, self::CACHE_TTL);
        }

        return $tree;
    }

    /**
     * 根据指定日期，把 tree 里每个节点的 images 替换为该日期的历史快照
     */
    private function applyHistoryImagesToTree(array &$tree, string $date): void
    {
        $category_ids = [];
        $this->collectCategoryIds($tree, $category_ids);
        if (empty($category_ids)) {
            return;
        }

        $map = (new RecycleCategoryQuoteHistoryService())->getSnapshotMapByDate($date, $category_ids);
        $this->fillImagesByMap($tree, $map);
    }

    private function collectCategoryIds(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            $ids[] = (int)$node['category_id'];
            if (!empty($node['child_list'])) {
                $this->collectCategoryIds($node['child_list'], $ids);
            }
        }
    }

    private function fillImagesByMap(array &$nodes, array $map): void
    {
        foreach ($nodes as &$node) {
            $cid = (int)$node['category_id'];
            $node['images'] = isset($map[$cid]) ? $map[$cid]['images'] : '';
            if (!empty($node['child_list'])) {
                $this->fillImagesByMap($node['child_list'], $map);
            }
        }
    }
  

    /**
     * 获取二手机分类信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'category_id,site_id,category_name,image,need_vip,level,pid,category_full_name,is_show,sort,create_time,update_time,images';

        $info = $this->model->field($field)->where([['category_id', "=", $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /** 移动端只消费报价分类，不开放完整分类增删改；权限不写入分类树缓存。 */
    public function quoteOptions(): array
    {
        $this->assertQuoteAccess(false);
        $site = (new AuthSiteService())->getSiteInfo();
        $canEdit = $this->hasQuotePermission(true)
            && !in_array((int)($site['status'] ?? SiteDict::CLOSE), [SiteDict::EXPIRE, SiteDict::CLOSE], true);
        return [
            'categories' => $this->getTree(),
            'can_edit' => $canEdit,
            'site_id' => (int)$this->site_id,
        ];
    }

    /**
     * 仅替换报价图。不能复用完整 edit() 的默认值，否则会覆盖分类名称、排序和会员限制。
     * 系统上传成功不等于发布；显式确认后，图片与历史快照在同一事务内生效。
     */
    public function updateQuoteImage(int $id, array $data): array
    {
        $this->assertQuoteAccess(true);
        if ($id <= 0) throw new AdminException('请选择有效的报价分类');
        $images = $data['images'] ?? null;
        $expected = $data['expected_images'] ?? null;
        $remark = $data['remark'] ?? '';
        if (!is_string($images) || !is_string($expected) || !is_string($remark)) {
            throw new AdminException('报价图片参数无效，请刷新页面后重新上传');
        }
        $images = trim($images);
        $urlParts = parse_url($images);
        $imagePath = is_array($urlParts) ? ($urlParts['path'] ?? null) : null;
        $scheme = strtolower((string)($urlParts['scheme'] ?? ''));
        $decodedPath = is_string($imagePath) ? rawurldecode(rawurldecode($imagePath)) : '';
        if ($images === '' || strlen($images) > 2048 || preg_match('/[\x00-\x20<>"\',]/', $images)
            || !in_array($scheme, ['', 'https'], true) || substr($images, 0, 2) === '//'
            || ($scheme === 'https' && empty($urlParts['host'])) || isset($urlParts['user']) || isset($urlParts['pass'])
            || !is_string($imagePath)
            || preg_match('/[\x00-\x20\\\\]/', $decodedPath) || preg_match('#(^|/)\.\.(/|$)#', $decodedPath)
            || !in_array(strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'], true)) {
            throw new AdminException('请先通过系统上传一张有效的报价图片，原报价保持不变');
        }
        if (strlen($expected) > 65535 || mb_strlen($remark, 'UTF-8') > 200) {
            throw new AdminException('报价备注过长，请控制在200字以内');
        }

        $result = Db::transaction(function () use ($id, $images, $expected, $remark): array {
            $category = $this->model->master(true)->where('category_id', $id)
                ->where('site_id', (int)$this->site_id)->lock(true)->findOrEmpty()->toArray();
            if (!$category) throw new AdminException('该报价分类不存在或不属于本站，公共报价分类只支持查看');
            $oldImages = (string)($category['images'] ?? '');
            // 响应丢失后的重试无需再次写版本，也无需重复确认旧值。
            if ($oldImages === $images) {
                return ['category_id' => $id, 'images' => $oldImages, 'update_time' => (int)($category['update_time'] ?? 0), 'changed' => false];
            }
            if ($oldImages !== $expected) {
                throw new AdminException('当前报价已被其他操作更新，请刷新当前报价后再确认，未覆盖现有图片');
            }

            $history = new RecycleCategoryQuoteHistoryService();
            if ($oldImages !== '') {
                $latestImages = (new RecycleCategoryQuoteHistory())->master(true)
                    ->where('site_id', (int)$this->site_id)->where('category_id', $id)
                    ->order('create_time desc,id desc')->value('images');
                if ((string)$latestImages !== $oldImages) {
                    $history->snapshot($id, $oldImages, '移动端更新前备份');
                }
            }
            $now = time();
            $this->model->where('category_id', $id)->where('site_id', (int)$this->site_id)
                ->update(['images' => $images, 'update_time' => $now]);
            $history->snapshot($id, $images, trim($remark) ?: '移动管理端更新报价');
            return ['category_id' => $id, 'images' => $images, 'update_time' => $now, 'changed' => true];
        });
        // 幂等重试也清缓存：前一次可能已提交，但客户端未收到响应或缓存清理暂时失败。
        $this->clearCategoryCache();
        return $result;
    }

    /**
     * 仅修改本站报价分类的客户可见状态，不联动图片、历史快照或子分类。
     * 两个状态均要求 JSON 整数 0/1，expected_is_show 用于阻止覆盖其他人的更新。
     */
    public function updateQuoteVisibility(int $id, array $data): array
    {
        $this->assertQuoteAccess(true);
        if ($id <= 0) throw new AdminException('请选择有效的报价分类');
        $isShow = $data['is_show'] ?? null;
        $expected = $data['expected_is_show'] ?? null;
        if (!in_array($isShow, [0, 1], true) || !in_array($expected, [0, 1], true)) {
            throw new AdminException('报价显示状态参数无效，必须传入整数0或1，请刷新页面后重试');
        }

        $result = Db::transaction(function () use ($id, $isShow, $expected): array {
            $category = $this->model->master(true)->where('category_id', $id)
                ->where('site_id', (int)$this->site_id)->lock(true)->findOrEmpty()->toArray();
            if (!$category) throw new AdminException('该报价分类不存在或不属于本站，公共报价分类只支持查看');
            $current = (int)$category['is_show'];
            // 当前已达目标时按幂等重试成功，不重复写入或重置更新时间。
            if ($current === $isShow) {
                return ['category_id' => $id, 'is_show' => $current, 'update_time' => (int)($category['update_time'] ?? 0), 'changed' => false];
            }
            if ($current !== $expected) {
                throw new AdminException('当前显示状态已被其他操作更新，请刷新后重试，未覆盖现有显示状态');
            }
            $now = time();
            $this->model->where('category_id', $id)->where('site_id', (int)$this->site_id)
                ->update(['is_show' => $isShow, 'update_time' => $now]);
            return ['category_id' => $id, 'is_show' => $isShow, 'update_time' => $now, 'changed' => true];
        });
        // 与图片更新共用同站点缓存；幂等重试也补清理已提交操作的缓存。
        $this->clearCategoryCache();
        return $result;
    }

    private function assertQuoteAccess(bool $write): void
    {
        if ((int)$this->site_id <= 0) throw new AdminException('请进入业务站点查看和更新报价单');
        // 仍走框架域名、停站、过期等校验；新接口再显式映射原有分类权限，覆盖升级无需初始化新菜单。
        (new AuthService())->checkRole($this->request);
        if ($write) {
            $site = (new AuthSiteService())->getSiteInfo();
            if (in_array((int)($site['status'] ?? SiteDict::CLOSE), [SiteDict::EXPIRE, SiteDict::CLOSE], true)) {
                throw new AdminException('当前站点已停用或过期，不能更新报价单');
            }
        }
        if (!$this->hasQuotePermission($write)) {
            throw new AdminException($write ? '需要报价分类编辑权限才能更新报价单' : '需要报价分类查看权限');
        }
    }

    private function hasQuotePermission(bool $write): bool
    {
        $auth = new AuthService();
        if (AuthService::isSuperAdmin()) return true;
        $role = $auth->getAuthRole((int)$this->site_id);
        if (!empty($role['is_admin'])) return true;
        $rules = $auth->getAuthApiList();
        return (bool)array_intersect(
            $write ? ['recycle/recycle_category/<id>', 'recycle/recycle_category/:id'] : ['recycle/recycle_category', 'recycle/recycle_category_tree'],
            $rules[$write ? 'put' : 'get'] ?? []
        );
    }

    /**
     * 添加商品分类
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data[ 'category_full_name' ] = $data[ 'category_name' ];
        $data[ 'level' ] = 1;
        $data['images'] = $data[ 'images' ];

        $condition = [
            [ 'site_id', '=', $this->site_id ],
            [ 'category_name', '=', $data[ 'category_name' ] ]
        ];
        if ($data[ 'pid' ] > 0) {
            $condition[] = [ 'pid', '=', $data[ 'pid' ] ];
        } else {
            $condition[] = [ 'level', '=', 1 ];
        }

        $categoryInfo = $this->model->where($condition)->findOrEmpty()->toArray();
        if ($categoryInfo) {
            throw new AdminException('分类已存在，请检查');
        }
        if ($data[ 'pid' ] > 0) {
            $info = $this->model->field("category_id, category_name")->where([ [ 'category_id', '=', $data[ 'pid' ] ] ])->findOrEmpty();
            if ($info->isEmpty()) throw new AdminException('SHOP_GOODS_CATEGORY_NOT_EXIST');
            $data[ 'category_full_name' ] = $info->category_name . '/' . $data[ 'category_name' ];
            $data[ 'level' ] = 2;
        }

        $data[ 'site_id' ] = $this->site_id;
        $data[ 'create_time' ] = time();
        $res = $this->model->create($data);

        if (!empty($data['images'])) {
            (new RecycleCategoryQuoteHistoryService())->snapshot(
                (int)$res->category_id,
                (string)$data['images'],
                $data['quote_remark'] ?? ''
            );
        }

        $this->clearCategoryCache();
        return $res->category_id;
    }

    /**
     * 商品分类编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
      
        $category_info = $this->getInfo($id);

        

        if ($category_info && $category_info[ 'category_id' ] != $id) {
            throw new AdminException('分类已存在，请检查');
        }
        
        // 检查是否有子分类，如果有子分类且要改变父级，则不允许
        if ($category_info[ 'level' ] == 1 && $category_info[ 'pid' ] != $data[ 'pid' ]) {
            // 查询子分类数量
            $child_count = $this->model->where([
                ['pid', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->count();
            
            if ($child_count > 0) {
                throw new AdminException('SHOP_GOODS_CATEGORY_EXIST_CHILD');
            }
        }

        $data[ 'category_full_name' ] = $data[ 'category_name' ];
        $data[ 'level' ] = 1;
        $data['images'] = $data[ 'images' ];
        if ($data[ 'pid' ] > 0) {
            $info = $this->model->field("category_id, category_name")->where([ [ 'category_id', '=', $data[ 'pid' ] ] ])->findOrEmpty();
            if ($info->isEmpty()) throw new AdminException('SHOP_GOODS_CATEGORY_NOT_EXIST');
            $data[ 'category_full_name' ] = $info->category_name . '/' . $data[ 'category_name' ];
            $data[ 'level' ] = 2;
        }
        $data[ 'sort' ] = $category_info[ 'sort' ];
        $data['need_vip'] = $data['need_vip'];
        $data[ 'update_time' ] = time();
        $this->model->where([ [ 'category_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])->update($data);

        $old_images = (string)($category_info['images'] ?? '');
        $new_images = (string)($data['images'] ?? '');
        if ($new_images !== '' && $new_images !== $old_images) {
            (new RecycleCategoryQuoteHistoryService())->snapshot(
                $id,
                $new_images,
                $data['quote_remark'] ?? ''
            );
        }

        $this->clearCategoryCache();
        return true;
    }

    // 拖拽editCategory 改变其sort
    public function updateCategory($data)
    {
        foreach ($data[ 'category_sort_array' ] as $key => $val) {
            $info = $this->model->field("category_id, category_name")->where([ [ 'category_id', '=', $val[ 'category_id' ] ], [ 'site_id', '=', $this->site_id ] ])->findOrEmpty();
            if (!$info->isEmpty()) {
                $data[ 'update_time' ] = time();
                $this->model->where([ [ 'category_id', '=', $val[ 'category_id' ] ], [ 'site_id', '=', $this->site_id ] ])->update([ 'sort' => $val[ 'sort' ] ]);
            }
        }
        $this->clearCategoryCache();
        return true;
    }

    /**
     * 删除二手机分类
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['category_id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        $this->clearCategoryCache();
        return $res;
    }

    private function cacheTag(): string
    {
        return 'hsx_recycle_category_' . $this->site_id;
    }

    private function cacheKey(string $scene): string
    {
        return $this->cacheTag() . '_' . $scene;
    }

    private function clearCategoryCache(): void
    {
        Cache::tag($this->cacheTag())->clear();
    }
}
