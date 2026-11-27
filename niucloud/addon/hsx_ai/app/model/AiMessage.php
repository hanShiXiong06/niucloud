<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\model;

use core\base\BaseModel;

final class AiMessage extends BaseModel
{
    protected $name = 'ai_message';
    protected $pk = 'id';
    protected $json = ['blocks_json', 'resources_json', 'actions_json'];
    protected $jsonAssoc = true;
}
