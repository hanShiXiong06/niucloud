<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpOpeningBatch extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_opening_batch';
    protected $autoWriteTimestamp = false;
    protected $json = ['summary_json', 'result_json'];
    protected $jsonAssoc = true;
}
