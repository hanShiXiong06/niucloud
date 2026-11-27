<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\model;

use core\base\BaseModel;

final class ProjectCenterDistributionDetail extends BaseModel
{
    protected $name = 'project_center_distribution_detail';
    protected $pk = 'id';
    protected $json = ['level_snapshot'];
    protected $jsonAssoc = true;
}
