<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\third_party;

use addon\hsx_recycle\app\service\core\address\AddressParseService;
use core\base\BaseAdminController;

/**
 * 地址解析
 */
class AddressParse extends BaseAdminController
{
    public function parse()
    {
        $data = $this->request->params([
            ['address', ''],
        ]);

        return success((new AddressParseService())->parse((int)$this->request->siteId(), (string)$data['address']));
    }
}
