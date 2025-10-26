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

namespace addon\home_service\app\api\controller\order;

use addon\home_service\app\dict\order\InvoiceDict;
use addon\home_service\app\service\api\order\InvoiceService;
use core\base\BaseApiController;


/**
 * 发票控制器
 * Class Invoice
 * @package addon\home_service\app\api\controller\invoice
 */
class Invoice extends BaseApiController
{
    /**
     * 发票状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', InvoiceDict::getStatus());
    }

    /**
     * 发票类型
     * @return \think\Response
     */
    public function type()
    {
        return success('SUCCESS', InvoiceDict::getType());
    }

    /**
     * 发票内容
     * @return \think\Response
     */
    public function content()
    {
        return success('SUCCESS', InvoiceDict::getContent());
    }

    /**
     * 抬头类型
     * @return \think\Response
     */
    public function headerType()
    {
        return success('SUCCESS', InvoiceDict::getHeaderType());
    }


    /**
     * 获取发票订单列表
     * @return \think\Response
     */
    public function getOrderPage()
    {
        return success(( new InvoiceService() )->getOrderPage());
    }

    /**
     * 获取发票列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            [ 'status', "all" ],
            [ 'header_type', "" ],
            [ 'type', "" ],
        ]);
        return success(( new InvoiceService() )->getPage($data));
    }

    /**
     * 发票详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success(( new InvoiceService() )->getInfo($id));
    }

    /**
     * 添加发票
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            [ "order_ids", [] ],
            [ "header_type", "" ],
            [ "header_name", "" ],
            [ "type", "" ], //发票类型
            [ "content", "" ], //发票内容
            [ "tax_number", "" ], //纳税人识别号
            [ "telephone", "" ], //注册电话
            [ "address", "" ], //注册地址
            [ "bank_name", "" ], //开户银行
            [ "email", "" ], //邮箱
            [ "bank_card_number", "" ], //银行账号
        ]);
        ( new InvoiceService() )->add($data);
        return success();
    }


}
