<?php
namespace addon\recycle\app\api\controller\payment;

use addon\recycle\app\service\api\payment\PaymentService;
use core\base\BaseApiController;
use think\App;

class Payment extends BaseApiController
{
    private $service;
    
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new PaymentService();
    }

    /**
     * 获取收款方式列表
     */
    public function lists()
    {
        return $this->service->getList($this->request->memberId());
    }

    /**
     * 添加收款方式
     */
    public function add()
    {
        $data = $this->request->post();
        $data['member_id'] = $this->request->memberId();
        return $this->service->add($data);
    }

    /**
     * 编辑收款方式
     */
    public function edit($id)
    {
        $data = $this->request->put();
        return $this->service->edit($id, $data, $this->request->memberId());
    }

    /**
     * 删除收款方式
     */
    public function del($id)
    {
        return $this->service->delete($id, $this->request->memberId());
    }

    /**
     * 设置默认收款方式
     */
    public function setDefault($id)
    {
        return $this->service->setDefault($id, $this->request->memberId());
    }
} 