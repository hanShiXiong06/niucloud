<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\model;

use core\base\BaseModel;

final class PerformanceFact extends BaseModel
{
    protected $name = 'performance_fact';
    protected $pk = 'id';
    protected $json = ['payload_json', 'dimensions_json', 'source_route_json'];
    protected $jsonAssoc = true;
}
