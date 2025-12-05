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

namespace addon\ai_image\app\api\controller\aiimagecard;
use core\base\BaseApiController;
use addon\ai_image\app\service\api\aiimagecard\AiimageCardService;


/**
 * 卡密兑换控制器
 * Class AiimageCard
 * @package addon\ai_image\app\adminapi\controller\aiimagecard
 */
class AiimageCard extends BaseApiController
{
   /**
    * 获取卡密兑换列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["card_num",""],
             ["is_use",""],
             ["is_export",""],
             ["pid",""]
        ]);
        return success((new AiimageCardService())->getPage($data));
    }

    public function verifyNum($card_num)
    {
        return success((new AiimageCardService())->verifyNum($card_num));
    }

    public function changeExport($id)
    {
        return success((new AiimageCardService())->changeExport($id));
    }
    public function delete($id){
        return success((new AiimageCardService())->delete($id));
    }
}
