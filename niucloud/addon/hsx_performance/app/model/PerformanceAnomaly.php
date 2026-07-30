<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\model;

use core\base\BaseModel;

final class PerformanceAnomaly extends BaseModel
{
    protected $name = 'performance_anomaly';
    protected $pk = 'id';
    protected $json = ['payload_json'];
    protected $jsonAssoc = true;
}
