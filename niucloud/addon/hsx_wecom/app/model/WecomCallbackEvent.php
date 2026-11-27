<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\model;

use core\base\BaseModel;

final class WecomCallbackEvent extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'wecom_callback_event';
    protected $autoWriteTimestamp = false;
    protected $json = ['payload_json'];
    protected $jsonAssoc = true;
}
