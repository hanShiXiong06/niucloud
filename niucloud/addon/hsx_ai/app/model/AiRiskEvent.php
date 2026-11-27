<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\model;

use core\base\BaseModel;

final class AiRiskEvent extends BaseModel
{
    protected $name = 'ai_risk_event';
    protected $pk = 'id';
    protected $json = ['evidence_json'];
    protected $jsonAssoc = true;
}
