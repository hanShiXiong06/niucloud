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

namespace addon\recycle\app\adminapi\controller\quotation;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\quotation\QuotationRequestService;
use core\exception\CommonException;

/**
 * 报价请求控制器
 * Class QuotationRequest
 * @package addon\recycle\app\adminapi\controller\quotation
 */
class QuotationRequest extends BaseAdminController
{
    /**
     * 获取请求记录列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['price_name', ''],
            ['request_status', ''],
        ]);
        return success((new QuotationRequestService())->getPage($data));
    }

    /**
     * 请求记录详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new QuotationRequestService())->getInfo($id));
    }

    /**
     * 手动发送请求
     * @return \think\Response
     */
    public function sendRequest()
    {
        $data = $this->request->params([
            ['config_id', 0],
        ]);
        
        if (empty($data['config_id'])) {
            throw new CommonException('请选择报价单配置');
        }
        
        $result = (new QuotationRequestService())->sendRequest($data['config_id']);
        return success('请求成功', $result);
    }
}

