<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\model;

use core\base\BaseModel;

final class AiToolRun extends BaseModel
{
    protected $name = 'ai_tool_run';
    protected $pk = 'id';
    protected $json = ['request_meta_json', 'response_meta_json'];
    protected $jsonAssoc = true;
}
