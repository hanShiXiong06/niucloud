<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\model;
use core\base\BaseModel;
final class ProjectCenterGroup extends BaseModel
{
    protected $name = 'project_center_group';
    protected $pk = 'id';
    protected $json = ['collaborator_uids'];
    protected $jsonAssoc = true;
}
