<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\model;

use core\base\BaseModel;

final class PerformanceReport extends BaseModel
{
    protected $name = 'performance_report';
    protected $pk = 'id';
    protected $json = ['summary_json', 'providers_json', 'snapshot_json'];
    protected $jsonAssoc = true;
}
