<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\model;
use core\base\BaseModel;
final class ProjectCenterReviewLog extends BaseModel
{
    protected $name = 'project_center_review_log';
    protected $pk = 'id';
    protected $json = ['field_issues_json', 'form_snapshot_json'];
    protected $jsonAssoc = true;
}
