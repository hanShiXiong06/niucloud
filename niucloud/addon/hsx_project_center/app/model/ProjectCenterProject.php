<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\model;
use core\base\BaseModel;
final class ProjectCenterProject extends BaseModel
{
    protected $name = 'project_center_project';
    protected $pk = 'id';
    protected $json = ['reviewer_uids', 'reviewer_role_ids', 'config_json'];
    protected $jsonAssoc = true;
}
