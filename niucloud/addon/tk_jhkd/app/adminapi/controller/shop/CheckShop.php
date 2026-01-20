<?php

namespace addon\tk_jhkd\app\adminapi\controller\shop;

use addon\tk_jhkd\app\service\admin\shop\OrderService;
use core\base\BaseAdminController;
use core\exception\CommonException;
class CheckShop extends BaseAdminController
{
   public function checkShop()
   {
       if (!in_array('shop', (new OrderService())->checkShop())) {
           throw new CommonException('需要搭配niucloud-shop商城使用，暂无使用权限');
       }else{
           return success('验证成功');
       }
   }
}
