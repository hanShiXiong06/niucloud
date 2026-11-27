<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpOpeningItem extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_opening_item';
    protected $autoWriteTimestamp = false;
    protected $json = ['raw_json', 'normalized_json'];
    protected $jsonAssoc = true;
}
