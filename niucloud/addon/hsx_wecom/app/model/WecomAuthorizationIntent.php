<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\model;

use core\base\BaseModel;

final class WecomAuthorizationIntent extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'wecom_authorization_intent';
    protected $autoWriteTimestamp = false;
    protected $json = ['meta_json'];
    protected $jsonAssoc = true;
}
