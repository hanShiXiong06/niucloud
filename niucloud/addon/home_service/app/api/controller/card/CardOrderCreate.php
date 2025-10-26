<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\api\controller\card;

use addon\home_service\app\service\api\card\CardOrderCreateService;
use core\base\BaseApiController;


/**
 * 次卡套餐订单控制器
 * Class CardOrderCreate
 * @package addon\home_service\app\api\controller\card
 */
class CardOrderCreate extends BaseApiController
{

    /**
     * 订单创建
     * @return void
     */
    public function create()
    {
        $data = $this->request->params([
            ['card_id', ''],
            ['city_id', ''],
        ]);
        return success('SUCCESS', (new CardOrderCreateService())->create($data));
    }
}
