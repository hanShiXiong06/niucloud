<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\model;

use core\base\BaseModel;

final class WecomCorpAuthorization extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'wecom_corp_authorization';
    protected $autoWriteTimestamp = false;
    protected $json = ['auth_info_json'];
    protected $jsonAssoc = true;
}
