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

namespace addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply;

use core\base\BaseAdminController;
use addon\wj_books\app\service\admin\wj_books_retrieve_apply\WjBooksRetrieveApplyService;
use think\Response;

/**
 * 取回申请控制器
 * Class WjBooksRetrieveApply
 * @package addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply
 */
class WjBooksRetrieveApply extends BaseAdminController
{
    /**
     * @var WjBooksRetrieveApplyService
     */
    protected $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new WjBooksRetrieveApplyService();
    }

    /**
     * 获取取回申请列表
     * @return Response
     */
    public function index()
    {
        $data = $this->request->params([
            ["page", 1],
            ["limit", 10],
            ["order_no", ""],
            ["status", ""],
            ["create_time", []]
        ]);
        return success($this->service->getPage($data));
    }

    /**
     * 获取取回申请详情
     * @param int $id
     * @return Response
     */
    public function read($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return error('', '无效的参数ID');
        }
        
        try {
            $info = $this->service->getDetail($id);
            return success($info);
        } catch (\Exception $e) {
            return error('', '获取详情失败：' . $e->getMessage());
        }
    }

    /**
     * 更新取回申请状态
     * @param int $id
     * @return Response
     */
    public function updateStatus($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return error('', '无效的参数ID');
        }
        
        $data = $this->request->params([
            ["status", "", true, "status", [0, 1, 2, 3]]
        ]);
        
        if ($data['status'] === "") {
            return error('', '状态不能为空');
        }
        
        $data['id'] = $id;
        return $this->service->updateStatus($data);
    }

    /**
     * 发货处理
     * @param int $id
     * @return Response
     */
    public function ship($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return error('', '无效的参数ID');
        }
        
        $data = $this->request->params([
            ["express_company", "", true],
            ["express_waybill", "", true]
        ]);
        
        if (empty($data['express_company'])) {
            return error('', '请填写物流公司');
        }
        
        if (empty($data['express_waybill'])) {
            return error('', '请填写物流单号');
        }
        
        $data['id'] = $id;
        return $this->service->ship($data);
    }

    /**
     * 完成取回申请
     * @param int $id
     * @return Response
     */
    public function complete($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return error('', '无效的参数ID');
        }
        return $this->service->complete($id);
    }

    /**
     * 取消取回申请
     * @param int $id
     * @return Response
     */
    public function cancel($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return error('', '无效的参数ID');
        }
        
        $data = $this->request->params([]);
        
        $data['id'] = $id;
        return $this->service->cancel($data);
    }

    /**
     * 删除取回申请
     * @param int $id
     * @return Response
     */
    public function delete($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return error('', '无效的参数ID');
        }
        return $this->service->delete($id);
    }
} 