<?php
declare(strict_types=1);
namespace addon\hsx_marketing\app\model;
use core\base\BaseModel;
final class MarketingFact extends BaseModel
{
    protected $name = 'marketing_fact';
    protected $pk = 'id';
    protected $json = ['payload_json'];
    protected $jsonAssoc = true;
}
