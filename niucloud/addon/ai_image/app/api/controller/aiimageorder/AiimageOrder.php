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

namespace addon\ai_image\app\api\controller\aiimageorder;

use core\base\BaseApiController;
use addon\ai_image\app\service\api\aiimageorder\AiimageOrderService;


/**
 * 订单列控制器
 * Class AiimageOrder
 * @package addon\ai_image\app\adminapi\controller\aiimageorder
 */
class AiimageOrder extends BaseApiController
{
    public function getSxfScan($id)
    {
        return success((new AiimageOrderService())->getSxfScan($id));
    }
    public function querySxfOrder($id)
    {
        return success((new AiimageOrderService())->querySxfOrder($id));
    }
   /**
    * 获取订单列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["package_id",""],
             ["order_id",""],
             ["name",""],
             ["image",""],
             ["status",""]
        ]);
        return success((new AiimageOrderService())->getPage($data));
    }

    /**
     * 订单列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new AiimageOrderService())->getInfo($id));
    }

    /**
     * 添加订单列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["package_id",0],
             ["order_id",""],
             ["name",""],
             ["image",""],
             ["order_money",0.00],
             ["point",0],
             ["num",0],
             ["type",""],
             ["day",0],
             ["status",0],
             ["out_trade_no",""],
             ["pay_time",0],
             ["pid",0],
             ["close_time",0]
        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimageorder\AiimageOrder.add');
        $data = (new AiimageOrderService())->add($data);
        return success('ADD_SUCCESS', $data);
    }

    /**
     * 订单列编辑
     * @param $id  订单列id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["package_id",0],
             ["order_id",""],
             ["name",""],
             ["image",""],
             ["order_money",0.00],
             ["point",0],
             ["num",0],
             ["type",""],
             ["day",0],
             ["status",0],
             ["out_trade_no",""],
             ["pay_time",0],
             ["pid",0],
             ["close_time",0]
        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimageorder\AiimageOrder.edit');
        (new AiimageOrderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 订单列删除
     * @param $id  订单列id
     * @return \think\Response
     */
    public function del(int $id){
        (new AiimageOrderService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new AiimageOrderService())->getMemberAll());
    }

    public function getAiimagePackageAll(){
         return success(( new AiimageOrderService())->getAiimagePackageAll());
    }

}
