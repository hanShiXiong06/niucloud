<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpRefurbishItem extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_refurbish_item';
    protected $autoWriteTimestamp = false;
}
