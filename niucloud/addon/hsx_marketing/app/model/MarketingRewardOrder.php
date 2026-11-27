<?php
declare(strict_types=1);
namespace addon\hsx_marketing\app\model;
use core\base\BaseModel;
final class MarketingRewardOrder extends BaseModel
{
    protected $name = 'marketing_reward_order';
    protected $pk = 'id';
    protected $json = ['reward_config_json', 'provider_result_json'];
    protected $jsonAssoc = true;
}
