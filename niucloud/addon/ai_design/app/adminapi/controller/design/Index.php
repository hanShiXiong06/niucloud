<?php

namespace addon\ai_design\app\adminapi\controller\design;

use app\adminapi\controller\BaseAdminController;
use app\service\api\admin\admin\AdminUserService;

/**
 * AI设计控制器
 */
class Index extends BaseAdminController
{
    /**
     * 设计列表
     */
    public function index()
    {
        $data = [
            'list' => [],
            'count' => 0
        ];
        
        return $this->success($data);
    }

    /**
     * 创建设计
     */
    public function create()
    {
        return $this->success('创建成功');
    }

    /**
     * 设计详情
     */
    public function detail()
    {
        return $this->success([]);
    }

    /**
     * 更新设计
     */
    public function update()
    {
        return $this->success('更新成功');
    }

    /**
     * 删除设计
     */
    public function delete()
    {
        return $this->success('删除成功');
    }
}

