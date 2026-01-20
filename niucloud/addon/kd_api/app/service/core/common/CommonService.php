<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\service\core\common;

use core\base\BaseCoreService;
use think\Exception;

/**
 * 机构端API统一服务类
 */
class CommonService extends BaseCoreService
{
    public function createKey()
    {
        // 生成32字节的随机字符串作为key
        return bin2hex(random_bytes(16));
    }

    public function createSecret()
    {
        // 生成64字节的随机字符串作为secret
        return bin2hex(random_bytes(32));
    }

    public function createShareCode($site_id)
    {
        $string = $site_id . time();
        return substr(md5($string), 3, 6);
    }

}
