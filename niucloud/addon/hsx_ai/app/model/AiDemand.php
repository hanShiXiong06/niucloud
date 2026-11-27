<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\model;

use core\base\BaseModel;

final class AiDemand extends BaseModel
{
    protected $name = 'ai_demand';
    protected $pk = 'id';
    protected $json = ['entities_json', 'requirements_json', 'source_message_ids_json'];
    protected $jsonAssoc = true;
}
