<?php
declare(strict_types=1);
namespace addon\hsx_marketing\app\model;
use core\base\BaseModel;
final class MarketingNoticeLog extends BaseModel
{
    protected $name = 'marketing_notice_log';
    protected $pk = 'id';
    protected $json = ['result_json'];
    protected $jsonAssoc = true;
}
