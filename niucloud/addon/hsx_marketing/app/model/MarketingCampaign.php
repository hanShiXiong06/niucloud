<?php
declare(strict_types=1);
namespace addon\hsx_marketing\app\model;
use core\base\BaseModel;
final class MarketingCampaign extends BaseModel
{
    protected $name = 'marketing_campaign';
    protected $pk = 'id';
    protected $json = ['allowed_level_ids', 'fact_filter_json', 'expire_notice_days', 'notice_channels'];
    protected $jsonAssoc = true;
}
