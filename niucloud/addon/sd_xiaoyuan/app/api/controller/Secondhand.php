<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\SecondhandService;
use core\base\BaseApiController;
use think\Response;

/**
 * 二手交易接口
 */
class Secondhand extends BaseApiController
{
    /**
     * 获取分类列表
     */
    public function categoryList(): Response
    {
        $list = (new SecondhandService())->getCategoryList();
        return success($list);
    }

    /**
     * 获取商品列表
     */
    public function list(): Response
    {
        $data = $this->request->params([
            ['category_id', ''],
            ['keyword', ''],
            ['school_id', ''],
            ['campus', ''],
            ['all_school', 0],
            ['sort', 'new'],
            ['goods_type', ''],
            ['is_urgent', 0],
            ['is_free', 0],
            ['page', 1],
            ['limit', 10],
        ]);
        
        if (empty($data['goods_type'])) {
            if (!empty($data['is_urgent'])) {
                $data['goods_type'] = 'urgent';
            } elseif (!empty($data['is_free'])) {
                $data['goods_type'] = 'free';
            }
        }
        
        // 只显示在售商品
        $data['status'] = 1;
        
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
        
        $info = (new SecondhandService())->getInfo((int)$id, true);
        
        return success($info);
    }

    /**
     * 发布商品
     */
    public function publish(): Response
    {
        $data = $this->request->params([
            ['category_id', 0],
            ['title', ''],
            ['content', ''],
            ['images', ''],
            ['original_price', 0],
            ['price', 0],
            ['condition_level', 9],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['contact_wechat', ''],
            ['trade_method', 'FACE'],
            ['trade_address', ''],
            ['school_id', 0],
            ['campus', ''],
            ['is_urgent', 0],
        ]);
        
        if (empty($data['title'])) {
            return fail('请填写完整信息');
        }
        if (!isset($data['price']) || $data['price'] === '' || !is_numeric($data['price'])) {
            return fail('请填写价格');
        }
        if ((float)$data['price'] < 0) {
            return fail('价格格式不正确');
        }
        
        $id = (new SecondhandService())->publish($this->request->memberId(), $data);
        return success(['id' => $id]);
    }

    /**
     * 编辑商品
     */
    public function edit(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['category_id', ''],
            ['title', ''],
            ['content', ''],
            ['images', ''],
            ['original_price', ''],
            ['price', ''],
            ['condition_level', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['contact_wechat', ''],
            ['trade_method', ''],
            ['trade_address', ''],
            ['is_urgent', 0],
        ]);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SecondhandService())->edit((int)$id, $this->request->memberId(), $data);
        return success('编辑成功');
    }

    /**
     * 下架商品
     */
    public function off(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SecondhandService())->off((int)$id, $this->request->memberId());
        return success('下架成功');
    }

    /**
     * 上架商品
     */
    public function on(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SecondhandService())->on((int)$id, $this->request->memberId());
        return success('上架成功');
    }

    /**
     * 标记已售出
     */
    public function sold(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SecondhandService())->sold((int)$id, $this->request->memberId());
        return success('操作成功');
    }

    /**
     * 删除商品
     */
    public function del(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new SecondhandService())->del((int)$id, $this->request->memberId());
        return success('删除成功');
    }

    /**
     * 想要商品
     */
    public function want(): Response
    {
        $goods_id = $this->request->param('goods_id', 0);
        $message = $this->request->param('message', '');
        
        if (empty($goods_id)) {
            return fail('参数错误');
        }
        
        (new SecondhandService())->want((int)$goods_id, $this->request->memberId(), $message);
        return success('操作成功');
    }

    /**
     * 我发布的商品
     */
    public function myPublish(): Response
    {
        $data = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $service = new SecondhandService();
        $list = $service->getMyPage($data);
        return success($list);
    }

    /**
     * 获取交易方式列表
     */
    public function tradeMethodList(): Response
    {
        $list = (new SecondhandService())->getTradeMethodList();
        return success($list);
    }
}
