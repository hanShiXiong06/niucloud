<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\model;

use core\base\BaseModel;

final class WecomProviderSuite extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'wecom_provider_suite';
    protected $autoWriteTimestamp = false;
}
