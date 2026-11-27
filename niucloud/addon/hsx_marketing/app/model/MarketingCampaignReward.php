<?php
declare(strict_types=1);
namespace addon\hsx_marketing\app\model;
use core\base\BaseModel;
final class MarketingCampaignReward extends BaseModel
{
    protected $name = 'marketing_campaign_reward';
    protected $pk = 'id';
    protected $json = ['reward_config_json'];
    protected $jsonAssoc = true;
}
