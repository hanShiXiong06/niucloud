<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\model;

use core\base\BaseModel;

final class PerformanceMetric extends BaseModel
{
    protected $name = 'performance_metric';
    protected $pk = 'id';
}
