<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

class Evaluate extends BaseModel
{
    protected $name = 'xiaoyuan_evaluate';
    protected $pk = 'id';
    
    // 禁用自动时间戳
    protected $autoWriteTimestamp = false;
}
