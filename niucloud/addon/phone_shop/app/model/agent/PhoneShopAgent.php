<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 站点代理订阅关系
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\model\agent;

use core\base\BaseModel;

/**
 * 商城站点代理订阅关系模型
 * 一行 = 「子站 agent_site_id 代理主站 master_site_id」+ 加价 + 是否订阅引用数据
 * Class PhoneShopAgent
 * @package addon\phone_shop\app\model\agent
 */
class PhoneShopAgent extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'phone_shop_agent';
}
