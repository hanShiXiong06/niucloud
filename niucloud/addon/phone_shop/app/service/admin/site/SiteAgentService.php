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

namespace addon\phone_shop\app\service\admin\site;

use core\base\BaseAdminService;
use addon\phone_shop\app\model\site\SiteAgent;
use addon\phone_shop\app\model\site\Site;
use core\exception\AdminException;

/**
 * 商户代理关系服务层
 * Class SiteAgentService
 * @package addon\phone_shop\app\service\admin\site
 */
class SiteAgentService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new SiteAgent();
    }

    /**
     * 获取代理关系列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        // 先获取自己的client 
        $site = (new Site())->where([['site_id', '=', $this->site_id]])->field('client')->find();
        
        $field = 'id, site_id, agent_site_id, status, create_time, update_time';
        $order = 'create_time desc';
        
        $condition = [];
        
        // 根据client类型确定查询条件
        if ($site['client'] == 1) {
            // client=1 代表是代理商，返回自己代理的站点列表
            $condition[] = ['site_id', '=', $this->site_id];
        } else {
            // client=0 代表是被代理方，返回代理自己的站点列表
            $condition[] = ['agent_site_id', '=', $this->site_id];
        }
        
        // 添加搜索条件
        if (!empty($where['agent_site_id'])) {
            $condition[] = ['agent_site_id', '=', $where['agent_site_id']];
        }
        if (isset($where['status'])) {
            $condition[] = ['status', '=', $where['status']];
        }
        
        $search_model = $this->model->where($condition)
            ->field($field)
            ->order($order);
            
        $list = $this->pageQuery($search_model);
        
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                if ($site['client'] == 1) {
                    // 如果是代理商，获取被代理方的名称
                    $agent_site = (new Site())->where([['site_id', '=', $item['agent_site_id']]])
                        ->field('site_name','client')
                        ->find();
                    $item['site_name'] = $agent_site ? $agent_site['site_name'] : '';
                    $item['client'] = $site['client'] ;
                } else {
                    // 如果是被代理方，获取代理商的名称
                    $agent_site = (new Site())->where([['site_id', '=', $item['site_id']]])
                        ->field('site_name','client')
                        ->find();
                    $item['site_name'] = $agent_site ? $agent_site['site_name'] : '';
                    $item['client'] = $site['client'];
                }
            }
        }
        
        return $list;
    }

    /**
     * 获取可代理的站点列表
     * @return array
     */
    public function getAvailableSites()
    {
        // 查询所有的站点  条件是  client = 0 
        $where = [ ['client', '=', 0] ];
        return (new Site())->where($where)->field('site_id,site_name')->select()->toArray();
    }
    /*
    寒假放假 一人偷偷在家使用cursor pua他写代码    
    */


    /**
     * 添加代理关系
     * @param array $data
     * @return int
     */
    public function add(array $data)
    {
        // 如果自己的client = 0 则提示无权限
        $site = (new Site())->where([['site_id', '=', $this->site_id]])->field('client')->find();
        if($site['client'] == 0){
            throw new AdminException('无权限');
        }

        // 直接通过Site 完成验证 判断client = 0 
        $where = [ ['client', '=', 0], ['site_id', '=', $data['agent_site_id']] ];
        $site = (new Site())->where($where)->field('site_id,site_name')->find();
        if(empty($site)){
            throw new AdminException('该站点不可被代理');
        }
        // 如果是自己 则不能添加
        if($data['agent_site_id'] == $this->site_id){
            throw new AdminException('不能代理自己');
        }

        // 检查是否已存在代理关系
        $exists = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['agent_site_id', '=', $data['agent_site_id']]
        ])->find();

        if ($exists) {
            throw new AdminException('已存在代理关系');
        }

        // 添加代理关系
        $data['site_id'] = $this->site_id;
        $data['status'] = 1;
        $data['create_time'] = time();
        $data['update_time'] = time();
        
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 修改代理状态
     * @param array $data
     * @return bool
     */
    public function modifyStatus(array $data)
    {
     
        $info = $this->model->where([
            ['id', '=', $data['id']],
            ['site_id', '=', $this->site_id]
        ])->find();
    
        if (!$info) {
            throw new AdminException('无权限修改代理关系,联系代理商');
        }

        $update = [
            'status' => $data['status'],
            'update_time' => time()
        ];
        
        return $this->model->where([['id', '=', $data['id']]])->update($update);
    }

    /**
     * 删除代理关系
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (!$info) {
            throw new AdminException('代理关系不存在或者无权限修改请联系对应的代理商');
        }

        return $info->delete();
    }

    /**
     * 获取商品代理关系
     * @param int $goods_id 商品ID
     * @param int $site_id 站点ID
     * @return array
     */
    public function getGoodsProxyInfo(int $goods_id, int $site_id)
    {
        $proxy_info = model('phone_shop_goods_proxy')->where([
            ['goods_id', '=', $goods_id],
            ['site_id', '=', $site_id]
        ])->find();

        if (empty($proxy_info)) {
            return [];
        }

        // 获取来源站点信息
        $source_site = model('site')->where([
            ['site_id', '=', $proxy_info['source_site_id']]
        ])->field('site_id, site_name')->find();

        return [
            'proxy_id' => $proxy_info['id'],
            'goods_id' => $proxy_info['goods_id'],
            'source_site' => $source_site ?? [],
            'markup_type' => $proxy_info['markup_type'],
            'markup_value' => $proxy_info['markup_value'],
            'status' => $proxy_info['status']
        ];
    }

    /**
     * 计算代理商品价格
     * @param float $original_price 原始价格
     * @param array $proxy_info 代理信息
     * @return float
     */
    public function calculateProxyPrice(float $original_price, array $proxy_info)
    {
        if (empty($proxy_info) || $proxy_info['status'] != 1) {
            return $original_price;
        }

        // 固定金额加价
        if ($proxy_info['markup_type'] == 1) {
            return $original_price + $proxy_info['markup_value'];
        }

        // 区间加价（百分比）
        if ($proxy_info['markup_type'] == 2) {
            return $original_price * (1 + $proxy_info['markup_value'] / 100);
        }

        return $original_price;
    }

    /**
     * 获取站点的所有代理商品
     * @param int $site_id 站点ID
     * @param array $params 查询参数
     * @return array
     */
    public function getProxyGoodsList(int $site_id, array $params = [])
    {
        $where = [
            ['p.site_id', '=', $site_id],
            ['p.status', '=', 1]
        ];

        // 构建查询条件
        if (!empty($params['keyword'])) {
            $where[] = ['g.goods_name', 'like', '%' . $params['keyword'] . '%'];
        }

        $field = 'p.id as proxy_id, p.goods_id, p.source_site_id, p.markup_type, p.markup_value, 
                 g.goods_name, g.goods_image, g.price as original_price, s.site_name as source_site_name';

        $list = model('phone_shop_goods_proxy')
            ->alias('p')
            ->join('phone_shop_goods g', 'p.goods_id = g.goods_id')
            ->join('site s', 'p.source_site_id = s.site_id')
            ->where($where)
            ->field($field)
            ->page($params['page'] ?? 1, $params['limit'] ?? 10)
            ->select()
            ->each(function ($item) {
                // 计算代理价格
                $item['proxy_price'] = $this->calculateProxyPrice($item['original_price'], [
                    'markup_type' => $item['markup_type'],
                    'markup_value' => $item['markup_value'],
                    'status' => 1
                ]);
                return $item;
            });

        $count = model('phone_shop_goods_proxy')
            ->alias('p')
            ->join('phone_shop_goods g', 'p.goods_id = g.goods_id')
            ->where($where)
            ->count();

        return [
            'list' => $list,
            'count' => $count,
            'page' => $params['page'] ?? 1,
            'limit' => $params['limit'] ?? 10
        ];
    }
}