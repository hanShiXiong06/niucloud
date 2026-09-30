<?php
declare(strict_types=1);
namespace addon\hsx_express\app\model;
use core\base\BaseModel;
final class ExpressTask extends BaseModel
{
    protected $name = 'hsx_express_task';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
