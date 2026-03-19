<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

class Appeal extends BaseModel
{
    protected $name = 'xiaoyuan_appeal';
    protected $pk = 'id';
    
    protected $type = [
        'images' => 'json'
    ];
}
