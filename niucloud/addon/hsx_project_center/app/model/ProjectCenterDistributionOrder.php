<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\model;

use core\base\BaseModel;

final class ProjectCenterDistributionOrder extends BaseModel
{
    protected $name = 'project_center_distribution_order';
    protected $pk = 'id';
    protected $json = ['rule_snapshot'];
    protected $jsonAssoc = true;
}
