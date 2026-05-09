<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\SecondhandService;
use addon\sd_xiaoyuan\app\model\SecondhandCategory;
use core\base\BaseAdminController;
use think\Response;

/**
 * 二手交易管理控制器
 */
class Secondhand extends BaseAdminController
{
    /**
     * 获取商品列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['category_id', ''],
            ['status', ''],
            ['school_id', ''],
            ['campus', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new SecondhandService())->getPage($data);
        return success($list);
    }

    /**
     * 获取商品详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new SecondhandService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 获取分类列表
     */
    public function categoryList(): Response
    {
        $list = (new SecondhandCategory())->where([
            ['site_id', '=', $this->request->siteId()]
        ])->order('sort desc,id asc')->select()->toArray();
        return success($list);
    }

    /**
     * 添加分类
     */
    public function addCategory(): Response
    {
        $data = $this->request->params([
            ['name', ''],
            ['icon', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        
        if (empty($data['name'])) {
            return fail('请填写分类名称');
        }
        
        $data['site_id'] = $this->request->siteId();
        $data['create_time'] = time();
        
        $res = (new SecondhandCategory())->create($data);
        return success(['id' => $res->id]);
    }

    /**
     * 编辑分类
     */
    public function editCategory(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['icon', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $category = (new SecondhandCategory())->where([['id', '=', $id], ['site_id', '=', $this->request->siteId()]])->find();
        if (empty($category)) {
            return fail('分类不存在');
        }
        
        $category->save($data);
        return success('编辑成功');
    }

    /**
     * 删除分类
     */
    public function delCategory(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $category = (new SecondhandCategory())->where([['id', '=', $id], ['site_id', '=', $this->request->siteId()]])->find();
        if (empty($category)) {
            return fail('分类不存在');
        }
        
        $category->delete();
        return success('删除成功');
    }

    /**
     * 审核商品
     */
    public function audit(): Response
    {
        $data = $this->request->params([
            ['id', 0],
            ['status', 0],
            ['refuse_reason', ''],
        ]);
        
        if (empty($data['id'])) {
            return fail('参数错误');
        }
        
        $goods = (new \addon\sd_xiaoyuan\app\model\Secondhand())->where([
            ['id', '=', $data['id']], 
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        
        if (empty($goods)) {
            return fail('商品不存在');
        }
        
        // status: 1=通过审核(在售), 2=拒绝审核(下架)
        $updateData = [];
        if ($data['status'] == 1) {
            // 审核通过，设置为在售状态
            $updateData['status'] = \addon\sd_xiaoyuan\app\model\Secondhand::STATUS_ON;
            $updateData['refuse_reason'] = '';
        } else if ($data['status'] == 2) {
            // 审核拒绝，设置为下架状态
            $updateData['status'] = \addon\sd_xiaoyuan\app\model\Secondhand::STATUS_OFF;
            $updateData['refuse_reason'] = $data['refuse_reason'] ?? '审核未通过';
        } else {
            return fail('状态参数错误');
        }
        
        $updateData['update_time'] = time();
        $goods->save($updateData);
        
        return success($data['status'] == 1 ? '审核通过' : '已拒绝');
    }

    /**
     * 删除商品
     */
    public function delete(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $goods = (new \addon\sd_xiaoyuan\app\model\Secondhand())->where([
            ['id', '=', $id], 
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        
        if (empty($goods)) {
            return fail('商品不存在');
        }
        
        $goods->delete();
        return success('删除成功');
    }

    /**
     * 编辑商品
     */
    public function edit(): Response
    {
        $data = $this->request->params([
            ['id', 0],
            ['title', ''],
            ['category_id', ''],
            ['original_price', 0],
            ['price', 0],
            ['condition_level', 9],
            ['trade_method', 'FACE'],
            ['trade_address', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['content', ''],
            ['images', ''],
        ]);
        
        if (empty($data['id'])) {
            return fail('参数错误');
        }
        
        if (empty($data['title'])) {
            return fail('请输入商品标题');
        }
        
        $goods = (new \addon\sd_xiaoyuan\app\model\Secondhand())->where([
            ['id', '=', $data['id']], 
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        
        if (empty($goods)) {
            return fail('商品不存在');
        }
        
        $updateData = [
            'title' => $data['title'],
            'category_id' => $data['category_id'],
            'original_price' => $data['original_price'],
            'price' => $data['price'],
            'condition_level' => $data['condition_level'],
            'trade_method' => $data['trade_method'],
            'trade_address' => $data['trade_address'],
            'contact_name' => $data['contact_name'],
            'contact_mobile' => $data['contact_mobile'],
            'content' => $data['content'],
            'images' => $data['images'],
            'update_time' => time(),
        ];
        
        $goods->save($updateData);
        return success('编辑成功');
    }
}
